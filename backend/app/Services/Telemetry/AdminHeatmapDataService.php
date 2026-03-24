<?php

namespace App\Services\Telemetry;

use App\Models\Campaign;
use App\Models\DeviceLocation;
use App\Models\Vehicle;

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
     *     motion?: string,
     *     normalization?: string
     * }  $filters
     * @return array{
     *     points: list<array<string, mixed>>,
     *     buckets: list<array<string, mixed>>,
     *     meta: array<string, mixed>
     * }
     */
    public function build(array $filters): array
    {
        $motion = $filters['motion'] ?? 'both';
        $normalization = $filters['normalization'] ?? 'p95';
        if (! in_array($normalization, ['max', 'p95', 'p99'], true)) {
            $normalization = 'p95';
        }

        $imeis = $this->resolveImeis($filters);

        if ($imeis === []) {
            return [
                'points' => [],
                'buckets' => [],
                'meta' => $this->emptyMeta($filters, $motion, $normalization),
            ];
        }

        $q = DeviceLocation::query()->whereIn('device_id', $imeis);
        DeviceLocationEventAtRange::apply($q, $filters['date_from'] ?? null, $filters['date_to'] ?? null);

        $buckets = DeviceLocationHeatmapBuckets::groupedDualCounts($q->clone());

        $samplesMoving = (int) $buckets->sum(fn ($r) => (int) $r->w_moving);
        $samplesStopped = (int) $buckets->sum(fn ($r) => (int) $r->w_stopped);
        $samplesTotal = $samplesMoving + $samplesStopped;

        $gamma = TelemetryHeatmapConfig::intensityGamma();

        $wMoving = $buckets->map(fn ($r) => (int) $r->w_moving)->all();
        $wStopped = $buckets->map(fn ($r) => (int) $r->w_stopped)->all();

        $capMoving = HeatmapIntensityNormalizer::capFromWeights($wMoving, $normalization);
        $capStopped = HeatmapIntensityNormalizer::capFromWeights($wStopped, $normalization);

        $rankMovingBatch = HeatmapIntensityNormalizer::rankPercentBelowBatch($wMoving);
        $rankStoppedBatch = HeatmapIntensityNormalizer::rankPercentBelowBatch($wStopped);

        $bucketsOut = [];
        $points = [];

        foreach ($buckets as $idx => $r) {
            $lat = (float) $r->lat;
            $lng = (float) $r->lng;
            $wm = (int) $r->w_moving;
            $ws = (int) $r->w_stopped;
            $wt = $wm + $ws;

            $im = HeatmapIntensityNormalizer::normalize($wm, $capMoving, $gamma);
            $is = HeatmapIntensityNormalizer::normalizeStopped($ws, $capStopped);

            $rankM = $rankMovingBatch[$idx];
            $rankS = $rankStoppedBatch[$idx];

            $bucketsOut[] = [
                'lat' => $lat,
                'lng' => $lng,
                'w_moving' => $wm,
                'w_stopped' => $ws,
                'w_total' => $wt,
                'intensity_moving' => $im,
                'intensity_stopped' => $is,
                'rank_moving_pct' => $rankM,
                'rank_stopped_pct' => $rankS,
            ];

            if ($motion === 'moving') {
                if ($wm > 0) {
                    $points[] = [
                        'lat' => $lat,
                        'lng' => $lng,
                        'intensity' => $im,
                        'w' => $wm,
                        'w_moving' => $wm,
                        'w_stopped' => $ws,
                        'layer' => 'moving',
                        'rank_pct' => $rankM,
                    ];
                }
            } elseif ($motion === 'stopped') {
                if ($ws > 0) {
                    $points[] = [
                        'lat' => $lat,
                        'lng' => $lng,
                        'intensity' => $is,
                        'w' => $ws,
                        'w_moving' => $wm,
                        'w_stopped' => $ws,
                        'layer' => 'stopped',
                        'rank_pct' => $rankS,
                    ];
                }
            } else {
                if ($wm > 0) {
                    $points[] = [
                        'lat' => $lat,
                        'lng' => $lng,
                        'intensity' => $im,
                        'w' => $wm,
                        'w_moving' => $wm,
                        'w_stopped' => $ws,
                        'layer' => 'moving',
                        'rank_pct' => $rankM,
                    ];
                }
            }
        }

        $mult = (int) config('telemetry.impression_sample_multiplier');

        return [
            'points' => $points,
            'buckets' => $bucketsOut,
            'meta' => [
                'imei_count' => count($imeis),
                'location_samples' => $samplesTotal,
                'location_samples_moving' => $samplesMoving,
                'location_samples_stopped' => $samplesStopped,
                'impressions' => $samplesTotal * $mult,
                'motion' => $motion,
                'scope' => $filters['scope'],
                'driving_distance_km' => 0,
                'driving_time_hours' => 0,
                'parking_time_hours' => 0,
                'data_source' => 'device_locations',
                'intensity_gamma' => $gamma,
                'intensity_stopped_power' => HeatmapIntensityNormalizer::STOPPED_INTENSITY_POWER,
                'normalization' => $normalization,
                'cap_moving' => $capMoving,
                'cap_stopped' => $capStopped,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyMeta(array $filters, string $motion, string $normalization): array
    {
        return [
            'imei_count' => 0,
            'location_samples' => 0,
            'location_samples_moving' => 0,
            'location_samples_stopped' => 0,
            'impressions' => 0,
            'motion' => $motion,
            'scope' => $filters['scope'],
            'driving_distance_km' => 0,
            'driving_time_hours' => 0,
            'parking_time_hours' => 0,
            'data_source' => 'device_locations',
            'intensity_gamma' => TelemetryHeatmapConfig::intensityGamma(),
            'intensity_stopped_power' => HeatmapIntensityNormalizer::STOPPED_INTENSITY_POWER,
            'normalization' => $normalization,
            'cap_moving' => 1,
            'cap_stopped' => 1,
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
