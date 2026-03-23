<?php

namespace App\Services\Telemetry;

use App\Models\Campaign;
use App\Models\DailyImpression;
use App\Models\DeviceLocation;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class DatabaseHeatmapDataService implements HeatmapDataServiceInterface
{
    public function fetchHeatmapData(int $campaignId, array $filters = []): array
    {
        $mode = $filters['mode'] ?? 'both';

        $vehicleIds = Campaign::query()
            ->findOrFail($campaignId)
            ->campaignVehicles()
            ->pluck('vehicle_id');

        if (! empty($filters['vehicle_ids'])) {
            $allowed = collect($filters['vehicle_ids'])->map('intval')->all();
            $vehicleIds = $vehicleIds->filter(fn (int $id) => in_array($id, $allowed, true));
        }

        $imeis = Vehicle::query()->whereIn('id', $vehicleIds)->pluck('imei')->filter()->values()->all();

        if ($imeis === []) {
            return [
                'points' => [],
                'metrics' => $this->emptyMetrics($campaignId, $mode),
            ];
        }

        $q = DeviceLocation::query()->whereIn('device_id', $imeis);
        if (! empty($filters['date_from'])) {
            $q->whereDate('event_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $q->whereDate('event_at', '<=', $filters['date_to']);
        }

        DeviceLocationMotionScope::applyAdvertiserMode($q, $mode);

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            $buckets = $q->clone()
                ->selectRaw('ROUND(CAST(latitude AS numeric), 3)::float AS lat, ROUND(CAST(longitude AS numeric), 3)::float AS lng, COUNT(*) AS w')
                ->groupByRaw('ROUND(CAST(latitude AS numeric), 3), ROUND(CAST(longitude AS numeric), 3)')
                ->get();
        } else {
            $buckets = $q->clone()
                ->selectRaw('ROUND(latitude, 3) AS lat, ROUND(longitude, 3) AS lng, COUNT(*) AS w')
                ->groupByRaw('ROUND(latitude, 3), ROUND(longitude, 3)')
                ->get();
        }

        $maxW = (int) ($buckets->max('w') ?: 1);
        $points = $buckets->map(fn ($r) => [
            'lat' => (float) $r->lat,
            'lng' => (float) $r->lng,
            'intensity' => HeatmapBucketIntensity::normalize((int) $r->w, $maxW),
        ])->values()->all();

        $metrics = $this->resolveMetrics($campaignId, $filters);
        $metrics['mode'] = $mode;
        $metrics['heatmap_motion'] = self::heatmapMotionLabel($mode);
        $metrics['campaign_id'] = $campaignId;
        $metrics['intensity_gamma'] = TelemetryHeatmapConfig::intensityGamma();

        return [
            'points' => $points,
            'metrics' => $metrics,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMetrics(int $campaignId, array $filters): array
    {
        $from = $filters['date_from'] ?? null;
        $to = $filters['date_to'] ?? null;

        $q = DailyImpression::query()->where('campaign_id', $campaignId);
        if ($from) {
            $q->whereDate('stat_date', '>=', $from);
        }
        if ($to) {
            $q->whereDate('stat_date', '<=', $to);
        }

        $impr = (int) $q->sum('impressions');
        $dist = (float) $q->sum('driving_distance_km');
        $parkMin = (int) $q->sum('parking_minutes');

        $svc = app(CampaignVehicleTelemetryService::class);
        $driveMinSessions = $svc->sumCampaignStopSessionMinutes($campaignId, 'driving', $from, $to);
        $parkMinSessions = $svc->sumCampaignStopSessionMinutes($campaignId, 'parking', $from, $to);

        if ($impr > 0 || $dist > 0 || $parkMin > 0 || $driveMinSessions > 0 || $parkMinSessions > 0) {
            $drivingHours = $driveMinSessions > 0
                ? round($driveMinSessions / 60, 1)
                : ($dist > 0 ? round($dist / 35, 1) : 0.0);
            $parkingHours = $parkMin > 0
                ? round($parkMin / 60, 1)
                : ($parkMinSessions > 0 ? round($parkMinSessions / 60, 1) : 0.0);

            return [
                'impressions' => $impr,
                'driving_distance_km' => round($dist, 2),
                'driving_time_hours' => $drivingHours,
                'parking_time_hours' => $parkingHours,
                'data_source' => 'daily_impressions',
            ];
        }

        $vehicleIds = Campaign::query()->findOrFail($campaignId)->campaignVehicles()->pluck('vehicle_id');
        $imeis = Vehicle::query()->whereIn('id', $vehicleIds)->pluck('imei')->filter()->values()->all();
        $locQ = DeviceLocation::query()->whereIn('device_id', $imeis);
        if ($from) {
            $locQ->whereDate('event_at', '>=', $from);
        }
        if ($to) {
            $locQ->whereDate('event_at', '<=', $to);
        }
        $mode = $filters['mode'] ?? 'both';
        DeviceLocationMotionScope::applyAdvertiserMode($locQ, $mode);
        $rawCount = $locQ->count();
        $mult = (int) config('telemetry.impression_sample_multiplier');

        return [
            'impressions' => $rawCount * $mult,
            'driving_distance_km' => 0.0,
            'driving_time_hours' => 0.0,
            'parking_time_hours' => 0.0,
            'data_source' => 'device_locations_raw',
            'location_samples' => $rawCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyMetrics(int $campaignId, string $mode): array
    {
        return [
            'impressions' => 0,
            'driving_distance_km' => 0.0,
            'driving_time_hours' => 0.0,
            'parking_time_hours' => 0.0,
            'mode' => $mode,
            'heatmap_motion' => self::heatmapMotionLabel($mode),
            'campaign_id' => $campaignId,
            'data_source' => 'none',
            'intensity_gamma' => TelemetryHeatmapConfig::intensityGamma(),
        ];
    }

    private static function heatmapMotionLabel(string $mode): string
    {
        return match ($mode) {
            'parking' => 'stopped',
            'driving' => 'moving',
            default => 'both',
        };
    }
}
