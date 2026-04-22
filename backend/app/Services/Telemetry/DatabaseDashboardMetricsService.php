<?php

namespace App\Services\Telemetry;

use App\Models\Campaign;
use App\Models\DailyImpression;
use App\Models\DeviceLocation;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Advertiser dashboard metrics from PostgreSQL (daily_impressions + optional raw locations),
 * aligned with {@see DatabaseHeatmapDataService} formulas.
 */
class DatabaseDashboardMetricsService implements DashboardMetricsServiceInterface
{
    public function advertiserSummary(User $user): array
    {
        $campaigns = $user->campaignsAsAdvertiser()->get();
        $active = (int) $campaigns->where('status', 'active')->count();
        $campaignIds = $campaigns->pluck('id');

        if ($campaignIds->isEmpty()) {
            return $this->payload($active, 0, 0.0, 0.0, 0.0, null);
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

            return $this->payload(
                $active,
                $impr,
                round($dist, 2),
                $drivingHours,
                $parkingHours,
                null,
            );
        }

        $rawCount = $this->countDeviceLocationsWithinCampaignWindows($campaigns);

        $mult = (int) config('telemetry.impression_sample_multiplier');

        return $this->payload(
            $active,
            $rawCount * $mult,
            0.0,
            0.0,
            0.0,
            $rawCount > 0
                ? 'Impressions estimated from location samples until daily aggregates are built (run telemetry jobs / daily impression rollup).'
                : null,
        );
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
