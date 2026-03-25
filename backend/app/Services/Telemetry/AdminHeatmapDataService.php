<?php

namespace App\Services\Telemetry;

use App\Models\Campaign;
use App\Models\DeviceLocation;
use App\Models\Vehicle;

/**
 * Admin heatmap: campaign | vehicle | vehicle group + period + moving OR stopped (single layer).
 *
 * Read path: {@see HeatmapRollupQueryService} when viewport + zoom are provided and rollup reads are enabled.
 * Legacy path: {@see DeviceLocationHeatmapBuckets} (on-the-fly GROUP BY) when fallback is allowed.
 */
class AdminHeatmapDataService
{
    public function __construct(
        private readonly HeatmapRollupQueryService $rollupQuery,
    ) {}

    /**
     * @param  array{
     *     scope: string,
     *     campaign_id?: int|null,
     *     vehicle_id?: int|null,
     *     vehicle_ids?: list<int>,
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     motion?: string,
     *     normalization?: string,
     *     south?: float|string|null,
     *     west?: float|string|null,
     *     north?: float|string|null,
     *     east?: float|string|null,
     *     zoom?: int|string|null
     * }  $filters
     * @return array{
     *     points: list<array<string, mixed>>,
     *     buckets: list<array<string, mixed>>,
     *     meta: array<string, mixed>
     * }
     */
    public function build(array $filters): array
    {
        $motion = $filters['motion'] ?? 'moving';
        if ($motion === 'both') {
            $motion = 'moving';
        }
        if (! in_array($motion, ['moving', 'stopped'], true)) {
            $motion = 'moving';
        }

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

        $rollupMode = $motion === 'stopped' ? HeatmapAggregationService::MODE_PARKING : HeatmapAggregationService::MODE_DRIVING;

        $viewport = HeatmapViewport::parse($filters);
        if (HeatmapViewport::shouldReadRollup($filters) && $viewport !== null) {
            $from = $filters['date_from'] ?? null;
            $to = $filters['date_to'] ?? null;
            if ($from && $to) {
                $bbox = [
                    'min_lat' => $viewport['min_lat'],
                    'max_lat' => $viewport['max_lat'],
                    'min_lng' => $viewport['min_lng'],
                    'max_lng' => $viewport['max_lng'],
                ];

                return $this->buildFromRollup($imeis, $from, $to, $rollupMode, $viewport['zoom'], $bbox, $normalization, $filters, $motion);
            }
        }

        if (! HeatmapViewport::legacyFallbackAllowed()) {
            return [
                'points' => [],
                'buckets' => [],
                'meta' => array_merge($this->emptyMeta($filters, $motion, $normalization), [
                    'heatmap_error' => 'viewport_required',
                    'heatmap_error_detail' => 'Provide south, west, north, east, and zoom for heatmap rollups, or enable TELEMETRY_HEATMAP_LEGACY_FALLBACK_NO_VIEWPORT.',
                ]),
            ];
        }

        return $this->buildLegacyOnTheFly($filters, $imeis, $motion, $normalization);
    }

    /**
     * @param  list<string>  $imeis
     * @param  array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}  $bbox
     */
    private function buildFromRollup(
        array $imeis,
        string $from,
        string $to,
        string $rollupMode,
        int $zoom,
        array $bbox,
        string $normalization,
        array $filters,
        string $motion
    ): array {
        $rows = $this->rollupQuery->fetchBuckets($imeis, $from, $to, $rollupMode, $zoom, $bbox, $normalization);
        $samplesInView = array_sum(array_column($rows, 'w'));
        $samplesTotal = $this->rollupQuery->sumSamplesInRange($imeis, $from, $to, $rollupMode, $zoom);

        $weights = array_column($rows, 'w');
        $rankBatch = HeatmapIntensityNormalizer::rankPercentBelowBatch($weights);

        $gamma = TelemetryHeatmapConfig::intensityGamma();
        $capMoving = 1;
        $capStopped = 1;
        if ($rollupMode === HeatmapAggregationService::MODE_DRIVING) {
            $capMoving = HeatmapIntensityNormalizer::capFromWeights($weights, $normalization);
        } else {
            $capStopped = HeatmapIntensityNormalizer::capFromWeights($weights, $normalization);
        }

        $bucketsOut = [];
        $points = [];
        foreach ($rows as $idx => $row) {
            $lat = $row['lat'];
            $lng = $row['lng'];
            $w = $row['w'];
            $intensity = $row['intensity'];
            $wm = $rollupMode === HeatmapAggregationService::MODE_DRIVING ? $w : 0;
            $ws = $rollupMode === HeatmapAggregationService::MODE_PARKING ? $w : 0;
            $rank = $rankBatch[$idx] ?? 0.0;

            $bucketsOut[] = [
                'lat' => $lat,
                'lng' => $lng,
                'w_moving' => $wm,
                'w_stopped' => $ws,
                'w_total' => $w,
                'intensity_moving' => $rollupMode === HeatmapAggregationService::MODE_DRIVING ? $intensity : 0.0,
                'intensity_stopped' => $rollupMode === HeatmapAggregationService::MODE_PARKING ? $intensity : 0.0,
                'rank_moving_pct' => $rollupMode === HeatmapAggregationService::MODE_DRIVING ? $rank : null,
                'rank_stopped_pct' => $rollupMode === HeatmapAggregationService::MODE_PARKING ? $rank : null,
            ];

            $points[] = [
                'lat' => $lat,
                'lng' => $lng,
                'intensity' => $intensity,
                'w' => $w,
                'w_moving' => $wm,
                'w_stopped' => $ws,
                'layer' => $motion === 'stopped' ? 'stopped' : 'moving',
                'rank_pct' => $rank,
            ];
        }

        $mult = (int) config('telemetry.impression_sample_multiplier');

        return [
            'points' => $points,
            'buckets' => $bucketsOut,
            'meta' => [
                'imei_count' => count($imeis),
                'location_samples' => $samplesTotal,
                'location_samples_moving' => $rollupMode === HeatmapAggregationService::MODE_DRIVING ? $samplesTotal : 0,
                'location_samples_stopped' => $rollupMode === HeatmapAggregationService::MODE_PARKING ? $samplesTotal : 0,
                'location_samples_viewport' => $samplesInView,
                'impressions' => $samplesTotal * $mult,
                'motion' => $motion,
                'scope' => $filters['scope'],
                'driving_distance_km' => 0,
                'driving_time_hours' => 0,
                'parking_time_hours' => 0,
                'data_source' => 'heatmap_cells_daily',
                'intensity_gamma' => $gamma,
                'intensity_stopped_power' => HeatmapIntensityNormalizer::STOPPED_INTENSITY_POWER,
                'normalization' => $normalization,
                'cap_moving' => $capMoving,
                'cap_stopped' => $capStopped,
                'heatmap_rollup' => true,
                'heatmap_zoom_tier' => HeatmapBucketStrategy::tierFromMapZoom($zoom),
            ],
        ];
    }

    /**
     * @param  list<string>  $imeis
     */
    private function buildLegacyOnTheFly(array $filters, array $imeis, string $motion, string $normalization): array
    {
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

            $im = HeatmapIntensityNormalizer::normalize($wm, $capMoving, $gamma);
            $is = HeatmapIntensityNormalizer::normalizeStopped($ws, $capStopped);

            $bucketsOut[] = [
                'lat' => $lat,
                'lng' => $lng,
                'w_moving' => $wm,
                'w_stopped' => $ws,
                'w_total' => $wm + $ws,
                'intensity_moving' => $im,
                'intensity_stopped' => $is,
                'rank_moving_pct' => $rankMovingBatch[$idx],
                'rank_stopped_pct' => $rankStoppedBatch[$idx],
            ];

            if ($motion === 'moving' && $wm > 0) {
                $points[] = [
                    'lat' => $lat,
                    'lng' => $lng,
                    'intensity' => $im,
                    'w' => $wm,
                    'w_moving' => $wm,
                    'w_stopped' => $ws,
                    'layer' => 'moving',
                    'rank_pct' => $rankMovingBatch[$idx],
                ];
            } elseif ($motion === 'stopped' && $ws > 0) {
                $points[] = [
                    'lat' => $lat,
                    'lng' => $lng,
                    'intensity' => $is,
                    'w' => $ws,
                    'w_moving' => $wm,
                    'w_stopped' => $ws,
                    'layer' => 'stopped',
                    'rank_pct' => $rankStoppedBatch[$idx],
                ];
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
                'heatmap_rollup' => false,
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
            'scope' => $filters['scope'] ?? 'vehicle',
            'driving_distance_km' => 0,
            'driving_time_hours' => 0,
            'parking_time_hours' => 0,
            'data_source' => 'none',
            'intensity_gamma' => TelemetryHeatmapConfig::intensityGamma(),
            'intensity_stopped_power' => HeatmapIntensityNormalizer::STOPPED_INTENSITY_POWER,
            'normalization' => $normalization,
            'cap_moving' => 1,
            'cap_stopped' => 1,
            'heatmap_rollup' => false,
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
