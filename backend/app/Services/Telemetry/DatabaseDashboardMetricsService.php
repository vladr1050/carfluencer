<?php

namespace App\Services\Telemetry;

use App\Models\Campaign;
use App\Models\DailyImpression;
use App\Models\DeviceLocation;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ImpressionEngine\CampaignImpressionSnapshotResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Advertiser dashboard metrics from PostgreSQL (daily_impressions + optional raw locations),
 * aligned with {@see DatabaseHeatmapDataService} formulas.
 */
class DatabaseDashboardMetricsService implements DashboardMetricsServiceInterface
{
    public function __construct(
        private readonly CampaignImpressionSnapshotResolver $impressionSnapshots,
    ) {}

    public function advertiserSummary(User $user): array
    {
        $campaigns = $user->campaignsAsAdvertiser()->get();
        $active = (int) $campaigns->where('status', 'active')->count();
        $campaignIds = $campaigns->pluck('id');

        if ($campaignIds->isEmpty()) {
            return $this->withImpressionEngine(
                $this->payload($active, 0, 0.0, 0.0, 0.0, null),
                $campaigns,
            );
        }

        [$impr, $dist, $parkMin] = $this->sumDailyImpressionsWithinCampaignWindows($campaigns);

        if ($impr > 0 || $dist > 0 || $parkMin > 0) {
            $svc = app(CampaignVehicleTelemetryService::class);
            [$driveMinSessions, $parkMinSessions] = $this->sumStopSessionMinutesWithinCampaignWindows($campaigns, $svc);

            $drivingHours = $driveMinSessions > 0
                ? round($driveMinSessions / 60, 1)
                : ($dist > 0 ? round($dist / 35, 1) : 0.0);
            $parkingHours = $parkMin > 0
                ? round($parkMin / 60, 1)
                : ($parkMinSessions > 0 ? round($parkMinSessions / 60, 1) : 0.0);

            return $this->withImpressionEngine(
                $this->payload(
                    $active,
                    $impr,
                    round($dist, 2),
                    $drivingHours,
                    $parkingHours,
                    null,
                ),
                $campaigns,
            );
        }

        $rawCount = $this->countDeviceLocationsWithinCampaignWindows($campaigns);

        $mult = (int) config('telemetry.impression_sample_multiplier');

        return $this->withImpressionEngine(
            $this->payload(
                $active,
                $rawCount * $mult,
                0.0,
                0.0,
                0.0,
                $rawCount > 0
                    ? 'Impressions estimated from location samples until daily aggregates are built (run telemetry jobs / daily impression rollup).'
                    : null,
            ),
            $campaigns,
        );
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  Collection<int, Campaign>  $campaigns
     * @return array<string, mixed>
     */
    private function withImpressionEngine(array $base, Collection $campaigns): array
    {
        return array_merge($base, [
            'impression_engine' => $this->buildImpressionEngineRollup($campaigns),
        ]);
    }

    /**
     * Sums {@see CampaignImpressionStat} gross rows where snapshot dates match each campaign’s
     * effective dashboard window (same as telemetry window: start_date..end_date capped at today).
     *
     * @param  Collection<int, Campaign>  $campaigns
     * @return array{
     *     total_gross_impressions: int|null,
     *     driving_impressions: int|null,
     *     parking_impressions: int|null,
     *     campaigns_with_snapshot: int,
     *     campaigns_in_scope: int,
     *     coverage: string
     * }
     */
    private function buildImpressionEngineRollup(Collection $campaigns): array
    {
        $totalGross = 0;
        $totalDriving = 0;
        $totalParking = 0;
        $withStat = 0;
        $eligible = 0;

        foreach ($campaigns as $campaign) {
            $from = $this->effectiveStatWindowStart($campaign);
            $to = $this->effectiveStatWindowEnd($campaign);
            if ($from > $to) {
                continue;
            }
            $eligible++;
            $stat = $this->impressionSnapshots->findLatestDone((int) $campaign->id, $from, $to);
            if ($stat !== null) {
                $withStat++;
                $totalGross += (int) $stat->total_gross_impressions;
                $totalDriving += (int) $stat->driving_impressions;
                $totalParking += (int) $stat->parking_impressions;
            }
        }

        $coverage = $eligible === 0
            ? 'none'
            : ($withStat === $eligible ? 'full' : ($withStat > 0 ? 'partial' : 'none'));

        return [
            'total_gross_impressions' => $withStat > 0 ? $totalGross : null,
            'driving_impressions' => $withStat > 0 ? $totalDriving : null,
            'parking_impressions' => $withStat > 0 ? $totalParking : null,
            'campaigns_with_snapshot' => $withStat,
            'campaigns_in_scope' => $eligible,
            'coverage' => $coverage,
        ];
    }

    /**
     * @param  Collection<int, Campaign>  $campaigns
     * @return array{0: int, 1: float, 2: int}
     */
    private function sumDailyImpressionsWithinCampaignWindows(Collection $campaigns): array
    {
        $impr = 0;
        $dist = 0.0;
        $parkMin = 0;

        foreach ($campaigns as $campaign) {
            $from = $this->effectiveStatWindowStart($campaign);
            $to = $this->effectiveStatWindowEnd($campaign);
            if ($from > $to) {
                continue;
            }

            $row = DailyImpression::query()
                ->where('campaign_id', $campaign->id)
                ->whereDate('stat_date', '>=', $from)
                ->whereDate('stat_date', '<=', $to)
                ->selectRaw('COALESCE(SUM(impressions), 0) as i, COALESCE(SUM(driving_distance_km), 0) as d, COALESCE(SUM(parking_minutes), 0) as p')
                ->first();

            $impr += (int) ($row->i ?? 0);
            $dist += (float) ($row->d ?? 0.0);
            $parkMin += (int) ($row->p ?? 0);
        }

        return [$impr, $dist, $parkMin];
    }

    /**
     * @param  Collection<int, Campaign>  $campaigns
     * @return array{0: int, 1: int}
     */
    private function sumStopSessionMinutesWithinCampaignWindows(Collection $campaigns, CampaignVehicleTelemetryService $svc): array
    {
        $driveMinSessions = 0;
        $parkMinSessions = 0;

        foreach ($campaigns as $campaign) {
            $from = $this->effectiveStatWindowStart($campaign);
            $to = $this->effectiveStatWindowEnd($campaign);
            if ($from > $to) {
                continue;
            }

            $vehicleIds = $campaign->campaignVehicles()->pluck('vehicle_id')->map(fn ($id) => (int) $id)->values()->all();
            if ($vehicleIds === []) {
                continue;
            }

            $driveMinSessions += $svc->sumCampaignStopSessionMinutes($campaign->id, 'driving', $from, $to, $vehicleIds);
            $parkMinSessions += $svc->sumCampaignStopSessionMinutes($campaign->id, 'parking', $from, $to, $vehicleIds);
        }

        return [$driveMinSessions, $parkMinSessions];
    }

    /**
     * @param  Collection<int, Campaign>  $campaigns
     */
    private function countDeviceLocationsWithinCampaignWindows(Collection $campaigns): int
    {
        $total = 0;

        foreach ($campaigns as $campaign) {
            $from = $this->effectiveStatWindowStart($campaign);
            $to = $this->effectiveStatWindowEnd($campaign);
            if ($from > $to) {
                continue;
            }

            $imeis = Vehicle::query()
                ->whereIn('id', $campaign->campaignVehicles()->pluck('vehicle_id'))
                ->pluck('imei')
                ->filter(fn ($i) => is_string($i) && $i !== '')
                ->values()
                ->all();

            if ($imeis === []) {
                continue;
            }

            $startAt = CarbonImmutable::parse($from, 'UTC')->startOfDay();
            $endAt = CarbonImmutable::parse($to, 'UTC')->endOfDay();

            $total += (int) DeviceLocation::query()
                ->whereIn('device_id', $imeis)
                ->where('event_at', '>=', $startAt)
                ->where('event_at', '<=', $endAt)
                ->count();
        }

        return $total;
    }

    /**
     * Inclusive calendar start for dashboard stats (UTC date string).
     */
    private function effectiveStatWindowStart(Campaign $campaign): string
    {
        if ($campaign->start_date !== null) {
            return $campaign->start_date->toDateString();
        }

        if ($campaign->created_at !== null) {
            return $campaign->created_at->copy()->utc()->toDateString();
        }

        return '1970-01-01';
    }

    /**
     * Inclusive calendar end for dashboard stats (UTC date string), capped at today.
     */
    private function effectiveStatWindowEnd(Campaign $campaign): string
    {
        $today = now('UTC')->toDateString();
        if ($campaign->end_date === null) {
            return $today;
        }

        return min($campaign->end_date->toDateString(), $today);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(
        int $activeCampaigns,
        int $impressions,
        float $drivingKm,
        float $drivingHours,
        float $parkingHours,
        ?string $note,
    ): array {
        return [
            'active_campaigns_count' => $activeCampaigns,
            'impressions' => $impressions,
            'driving_distance_km' => $drivingKm,
            'driving_time_hours' => $drivingHours,
            'parking_time_hours' => $parkingHours,
            'note' => $note,
            'source' => 'database',
        ];
    }
}
