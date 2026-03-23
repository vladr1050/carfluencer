<?php

namespace App\Services\Telemetry;

use App\Models\Campaign;
use App\Models\DailyImpression;
use App\Models\DeviceLocation;
use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DatabaseHeatmapDataService implements HeatmapDataServiceInterface
{
    public function fetchHeatmapData(int $campaignId, array $filters = []): array
    {
        $mode = $filters['mode'] ?? 'both';

        $vehicleIds = $this->heatmapVehicleIds($campaignId, $filters);

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
     * Vehicle IDs attached to the campaign, optionally narrowed by heatmap filters.
     *
     * @return Collection<int, int>
     */
    private function heatmapVehicleIds(int $campaignId, array $filters): Collection
    {
        $vehicleIds = Campaign::query()
            ->findOrFail($campaignId)
            ->campaignVehicles()
            ->pluck('vehicle_id');

        if (! empty($filters['vehicle_ids'])) {
            $allowed = collect($filters['vehicle_ids'])->map('intval')->all();
            $vehicleIds = $vehicleIds->filter(fn (int $id) => in_array($id, $allowed, true));
        }

        return $vehicleIds->values();
    }

    /**
     * Driving distance (km) from GPS segments in range; same speed rule as daily rollup (>5 km/h or unknown speed counts).
     *
     * @param  list<string>  $imeis
     */
    private function estimateDrivingDistanceKmForDateRange(array $imeis, ?string $from, ?string $to): float
    {
        $total = 0.0;
        foreach ($imeis as $imei) {
            $q = DeviceLocation::query()
                ->where('device_id', $imei)
                ->orderBy('event_at');
            if ($from) {
                $q->whereDate('event_at', '>=', $from);
            }
            if ($to) {
                $q->whereDate('event_at', '<=', $to);
            }
            // Stream rows — loading all points with get() OOMs production heatmap requests on large fleets/ranges.
            $prev = null;
            foreach ($q->clone()->orderBy('id')->cursor(['id', 'latitude', 'longitude', 'speed']) as $p) {
                if ($prev !== null) {
                    $sp = $p->speed !== null ? (float) $p->speed : null;
                    if ($sp === null || $sp > 5) {
                        $total += GeoMath::haversineKm(
                            (float) $prev->latitude,
                            (float) $prev->longitude,
                            (float) $p->latitude,
                            (float) $p->longitude
                        );
                    }
                }
                $prev = $p;
            }
        }

        return $total;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMetrics(int $campaignId, array $filters): array
    {
        $from = $filters['date_from'] ?? null;
        $to = $filters['date_to'] ?? null;

        $vehicleIdsArr = $this->heatmapVehicleIds($campaignId, $filters)->all();
        $imeis = Vehicle::query()->whereIn('id', $vehicleIdsArr)->pluck('imei')->filter()->values()->all();

        if ($vehicleIdsArr === [] || $imeis === []) {
            return [
                'impressions' => 0,
                'driving_distance_km' => 0.0,
                'driving_time_hours' => 0.0,
                'parking_time_hours' => 0.0,
                'data_source' => 'none',
            ];
        }

        $q = DailyImpression::query()
            ->where('campaign_id', $campaignId)
            ->whereIn('vehicle_id', $vehicleIdsArr);
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
        $driveMinSessions = $svc->sumCampaignStopSessionMinutes($campaignId, 'driving', $from, $to, $vehicleIdsArr);
        $parkMinSessions = $svc->sumCampaignStopSessionMinutes($campaignId, 'parking', $from, $to, $vehicleIdsArr);

        if ($impr > 0 || $dist > 0 || $parkMin > 0 || $driveMinSessions > 0 || $parkMinSessions > 0) {
            $estimatedDist = 0.0;
            if ($dist <= 0.0 && ($impr > 0 || $driveMinSessions > 0 || $parkMinSessions > 0)) {
                $estimatedDist = $this->estimateDrivingDistanceKmForDateRange($imeis, $from, $to);
            }
            $effectiveDist = $dist > 0 ? $dist : $estimatedDist;

            $drivingHours = $driveMinSessions > 0
                ? round($driveMinSessions / 60, 1)
                : ($effectiveDist > 0 ? round($effectiveDist / 35, 1) : 0.0);
            $parkingHours = $parkMin > 0
                ? round($parkMin / 60, 1)
                : ($parkMinSessions > 0 ? round($parkMinSessions / 60, 1) : 0.0);

            $dataSource = ($dist <= 0.0 && $estimatedDist > 0.0) ? 'daily_impressions_estimated' : 'daily_impressions';

            return [
                'impressions' => $impr,
                'driving_distance_km' => round($effectiveDist, 2),
                'driving_time_hours' => $drivingHours,
                'parking_time_hours' => $parkingHours,
                'data_source' => $dataSource,
            ];
        }

        $locQ = DeviceLocation::query()->whereIn('device_id', $imeis);
        if ($from) {
            $locQ->whereDate('event_at', '>=', $from);
        }
        if ($to) {
            $locQ->whereDate('event_at', '<=', $to);
        }
        $rawCount = $locQ->count();
        $mult = (int) config('telemetry.impression_sample_multiplier');

        $estDist = $this->estimateDrivingDistanceKmForDateRange($imeis, $from, $to);
        $driveMinSessionsFb = $svc->sumCampaignStopSessionMinutes($campaignId, 'driving', $from, $to, $vehicleIdsArr);
        $parkMinSessionsFb = $svc->sumCampaignStopSessionMinutes($campaignId, 'parking', $from, $to, $vehicleIdsArr);

        $drivingHoursFb = $driveMinSessionsFb > 0
            ? round($driveMinSessionsFb / 60, 1)
            : ($estDist > 0 ? round($estDist / 35, 1) : 0.0);
        $parkingHoursFb = $parkMinSessionsFb > 0 ? round($parkMinSessionsFb / 60, 1) : 0.0;

        $hasTelemetry = $estDist > 0.0 || $driveMinSessionsFb > 0 || $parkMinSessionsFb > 0;

        return [
            'impressions' => $rawCount * $mult,
            'driving_distance_km' => round($estDist, 2),
            'driving_time_hours' => $drivingHoursFb,
            'parking_time_hours' => $parkingHoursFb,
            'data_source' => $hasTelemetry ? 'device_locations_estimated' : 'device_locations_raw',
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
