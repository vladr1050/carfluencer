<?php

namespace App\Services\Telemetry;

use App\Models\Campaign;
use App\Models\DeviceLocation;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

/**
 * Admin heatmap: multiple vehicles + period + moving/stopped/both (aligned with StopSessionBuilder parking rule).
 */
class AdminHeatmapDataService
{
    /**
     * @param  array{
     *     scope: string,
     *     campaign_id?: int|null,
     *     vehicle_id?: int|null,
     *     vehicle_ids?: list<int>,
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     motion?: string
     * }  $filters
     * @return array{points: list<array{lat: float, lng: float, intensity: float}>, meta: array<string, mixed>}
     */
    public function build(array $filters): array
    {
        $motion = $filters['motion'] ?? 'both';
        $imeis = $this->resolveImeis($filters);

        if ($imeis === []) {
            return [
                'points' => [],
                'meta' => [
                    'imei_count' => 0,
                    'location_samples' => 0,
                    'motion' => $motion,
                    'scope' => $filters['scope'],
                    'intensity_gamma' => TelemetryHeatmapConfig::intensityGamma(),
                ],
            ];
        }

        $q = DeviceLocation::query()->whereIn('device_id', $imeis);

        if (! empty($filters['date_from'])) {
            $q->whereDate('event_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $q->whereDate('event_at', '<=', $filters['date_to']);
        }

        DeviceLocationMotionScope::apply($q, $motion);

        $samples = (int) $q->clone()->count();

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

        $mult = (int) config('telemetry.impression_sample_multiplier');

        return [
            'points' => $points,
            'meta' => [
                'imei_count' => count($imeis),
                'location_samples' => $samples,
                'impressions' => $samples * $mult,
                'motion' => $motion,
                'scope' => $filters['scope'],
                'driving_distance_km' => 0,
                'driving_time_hours' => 0,
                'parking_time_hours' => 0,
                'data_source' => 'device_locations',
                'intensity_gamma' => TelemetryHeatmapConfig::intensityGamma(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<string>
     */
    private function resolveImeis(array $filters): array
    {
        $scope = $filters['scope'] ?? 'vehicle';

        if ($scope === 'campaign') {
            $campaignId = (int) ($filters['campaign_id'] ?? 0);
            if ($campaignId <= 0) {
                return [];
            }
            $campaign = Campaign::query()->find($campaignId);
            if ($campaign === null) {
                return [];
            }

            return $campaign->vehicles()
                ->whereNotNull('imei')
                ->where('imei', '!=', '')
                ->pluck('imei')
                ->unique()
                ->values()
                ->all();
        }

        if ($scope === 'vehicle') {
            $vehicleId = (int) ($filters['vehicle_id'] ?? 0);
            if ($vehicleId <= 0) {
                return [];
            }
            $v = Vehicle::query()->find($vehicleId);
            if ($v === null || $v->imei === null || $v->imei === '') {
                return [];
            }

            return [(string) $v->imei];
        }

        if ($scope === 'vehicles') {
            $ids = array_values(array_filter(array_map('intval', $filters['vehicle_ids'] ?? [])));
            if ($ids === []) {
                return [];
            }

            return Vehicle::query()
                ->whereIn('id', $ids)
                ->whereNotNull('imei')
                ->where('imei', '!=', '')
                ->pluck('imei')
                ->unique()
                ->values()
                ->all();
        }

        return [];
    }
}
