<?php

namespace App\Services\Telemetry;

use App\Models\CampaignVehicle;
use App\Models\DailyImpression;
use App\Models\DeviceLocation;
use App\Models\User;
use App\Models\Vehicle;

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

        $q = DailyImpression::query()->whereIn('campaign_id', $campaignIds);
        $impr = (int) $q->sum('impressions');
        $dist = (float) $q->sum('driving_distance_km');
        $parkMin = (int) $q->sum('parking_minutes');

        if ($impr > 0 || $dist > 0 || $parkMin > 0) {
            $vehicleIds = CampaignVehicle::query()
                ->whereIn('campaign_id', $campaignIds)
                ->pluck('vehicle_id');
            $imeis = Vehicle::query()
                ->whereIn('id', $vehicleIds)
                ->pluck('imei')
                ->filter()
                ->values()
                ->all();

            $svc = app(CampaignVehicleTelemetryService::class);
            $driveMinSessions = $svc->sumStopSessionMinutesForImeis($imeis, 'driving', null, null);
            $parkMinSessions = $svc->sumStopSessionMinutesForImeis($imeis, 'parking', null, null);

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

        $vehicleIds = CampaignVehicle::query()
            ->whereIn('campaign_id', $campaignIds)
            ->pluck('vehicle_id');

        $imeis = Vehicle::query()
            ->whereIn('id', $vehicleIds)
            ->pluck('imei')
            ->filter()
            ->values()
            ->all();

        $rawCount = $imeis !== []
            ? (int) DeviceLocation::query()->whereIn('device_id', $imeis)->count()
            : 0;

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
