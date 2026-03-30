<?php

namespace App\Services\Telemetry;

use App\Models\DeviceLocation;
use App\Models\Vehicle;

/**
 * Viewport- and zoom-dependent heatmap geometry (rollup or legacy buckets).
 */
class HeatmapMapQueryService
{
    public function __construct(
        private readonly HeatmapRollupQueryService $rollupQuery,
    ) {}

    /**
     * @return array{map: array<string, mixed>, debug: array<string, mixed>}
     */
    public function fetchMapAndDebug(HeatmapPageQuery $query): array
    {
        $filters = $query->toFiltersArray();
        $campaignId = $query->campaignId;
        $mode = $query->mode;
        $normalization = $query->normalization;

        $vehicleIds = $query->resolveCampaignVehicleIds();
        $imeis = Vehicle::query()->whereIn('id', $vehicleIds)->pluck('imei')->filter()->values()->all();

        if ($imeis === []) {
            return $this->emptyMapAndDebug($campaignId, $mode, $normalization);
        }

        $viewport = HeatmapViewport::parse($filters);
        if (HeatmapViewport::shouldReadRollup($filters) && $viewport !== null) {
            $from = $query->dateFrom;
            $to = $query->dateTo;
            if ($from && $to) {
                return $this->fetchFromRollup($campaignId, $query, $imeis, $mode, $normalization, $viewport);
            }
        }

        if (! HeatmapViewport::legacyFallbackAllowed()) {
            $gamma = TelemetryHeatmapConfig::intensityGamma();

            return [
                'map' => [
                    'points' => [],
                    'buckets' => [],
                    'mode' => $mode,
                    'heatmap_motion' => self::heatmapMotionLabel($mode),
                    'campaign_id' => $campaignId,
                    'normalization' => $normalization,
                    'heatmap_rollup' => false,
                ],
                'debug' => [
                    'intensity_gamma' => $gamma,
                    'intensity_stopped_power' => HeatmapIntensityNormalizer::STOPPED_INTENSITY_POWER,
                    'cap_moving' => 1,
                    'cap_stopped' => 1,
                    'cap_total' => 1,
                    'heatmap_error' => 'viewport_required',
                    'heatmap_error_detail' => 'Send south, west, north, east, zoom with the heatmap request, or enable TELEMETRY_HEATMAP_LEGACY_FALLBACK_NO_VIEWPORT.',
                ],
            ];
        }

        return $this->fetchLegacyGrouped($campaignId, $query, $imeis, $mode, $normalization);
    }

    /**
     * @param  list<string>  $imeis
     * @param  array{min_lat: float, max_lat: float, min_lng: float, max_lng: float, zoom: int}  $viewport
     * @return array{map: array<string, mixed>, debug: array<string, mixed>}
     */
    private function fetchFromRollup(
        int $campaignId,
        HeatmapPageQuery $query,
        array $imeis,
        string $mode,
        string $normalization,
        array $viewport
    ): array {
        $from = (string) $query->dateFrom;
        $to = (string) $query->dateTo;
        $bbox = [
            'min_lat' => $viewport['min_lat'],
            'max_lat' => $viewport['max_lat'],
            'min_lng' => $viewport['min_lng'],
            'max_lng' => $viewport['max_lng'],
        ];
        $zoom = $viewport['zoom'];

        $rows = $this->rollupQuery->fetchBuckets(
            $imeis,
            $from,
            $to,
            $mode,
            $zoom,
            $bbox,
            $normalization,
            $query->maxRollupCells,
        );
        $gamma = TelemetryHeatmapConfig::intensityGamma();
        $weights = array_column($rows, 'w');
        $rankBatch = HeatmapIntensityNormalizer::rankPercentBelowBatch($weights);
        $capMoving = $mode === 'driving'
            ? HeatmapIntensityNormalizer::capFromWeights($weights, $normalization)
            : 1;
        $capStopped = $mode === 'parking'
            ? HeatmapIntensityNormalizer::capFromWeights($weights, $normalization)
            : 1;

        $bucketsOut = [];
        $points = [];
        foreach ($rows as $idx => $row) {
            $lat = $row['lat'];
            $lng = $row['lng'];
            $w = $row['w'];
            $intensity = $row['intensity'];
            $wm = $mode === 'driving' ? $w : 0;
            $ws = $mode === 'parking' ? $w : 0;

            $bucketsOut[] = [
                'lat' => $lat,
                'lng' => $lng,
                'w_moving' => $wm,
                'w_stopped' => $ws,
                'w_total' => $w,
                'intensity_moving' => $mode === 'driving' ? $intensity : 0.0,
                'intensity_stopped' => $mode === 'parking' ? $intensity : 0.0,
                'rank_moving_pct' => $mode === 'driving' ? ($rankBatch[$idx] ?? 0.0) : null,
                'rank_stopped_pct' => $mode === 'parking' ? ($rankBatch[$idx] ?? 0.0) : null,
            ];

            $points[] = [
                'lat' => $lat,
                'lng' => $lng,
                'intensity' => $intensity,
                'w' => $w,
                'w_moving' => $wm,
                'w_stopped' => $ws,
            ];
        }

        $samplesTotal = $this->rollupQuery->sumSamplesInRange($imeis, $from, $to, $mode, $zoom);

        return [
            'map' => [
                'points' => $points,
                'buckets' => $bucketsOut,
                'mode' => $mode,
                'heatmap_motion' => self::heatmapMotionLabel($mode),
                'campaign_id' => $campaignId,
                'normalization' => $normalization,
                'heatmap_rollup' => true,
            ],
            'debug' => [
                'intensity_gamma' => $gamma,
                'intensity_stopped_power' => HeatmapIntensityNormalizer::STOPPED_INTENSITY_POWER,
                'cap_moving' => $capMoving,
                'cap_stopped' => $capStopped,
                'cap_total' => max($capMoving, $capStopped),
                'location_samples' => $samplesTotal,
                'heatmap_zoom_tier' => HeatmapBucketStrategy::tierFromMapZoom($zoom),
                'location_samples_viewport' => array_sum($weights),
            ],
        ];
    }

    /**
     * @param  list<string>  $imeis
     * @return array{map: array<string, mixed>, debug: array<string, mixed>}
     */
    private function fetchLegacyGrouped(
        int $campaignId,
        HeatmapPageQuery $query,
        array $imeis,
        string $mode,
        string $normalization
    ): array {
        $q = DeviceLocation::query()->whereIn('device_id', $imeis);
        DeviceLocationEventAtRange::apply($q, $query->dateFrom, $query->dateTo);

        $bucketsRaw = DeviceLocationHeatmapBuckets::groupedDualCounts($q->clone());

        $gamma = TelemetryHeatmapConfig::intensityGamma();
        $wMoving = $bucketsRaw->map(fn ($r) => (int) $r->w_moving)->all();
        $wStopped = $bucketsRaw->map(fn ($r) => (int) $r->w_stopped)->all();

        $capMoving = HeatmapIntensityNormalizer::capFromWeights($wMoving, $normalization);
        $capStopped = HeatmapIntensityNormalizer::capFromWeights($wStopped, $normalization);

        $rankMovingBatch = HeatmapIntensityNormalizer::rankPercentBelowBatch($wMoving);
        $rankStoppedBatch = HeatmapIntensityNormalizer::rankPercentBelowBatch($wStopped);

        $bucketsOut = [];
        $points = [];

        foreach ($bucketsRaw as $idx => $r) {
            $lat = (float) $r->lat;
            $lng = (float) $r->lng;
            $wm = (int) $r->w_moving;
            $ws = (int) $r->w_stopped;
            $wt = $wm + $ws;

            $im = HeatmapIntensityNormalizer::normalize($wm, $capMoving, $gamma);
            $is = HeatmapIntensityNormalizer::normalizeStopped($ws, $capStopped);

            $bucketsOut[] = [
                'lat' => $lat,
                'lng' => $lng,
                'w_moving' => $wm,
                'w_stopped' => $ws,
                'w_total' => $wt,
                'intensity_moving' => $im,
                'intensity_stopped' => $is,
                'rank_moving_pct' => $rankMovingBatch[$idx],
                'rank_stopped_pct' => $rankStoppedBatch[$idx],
            ];

            if ($mode === 'driving' && $wm > 0) {
                $points[] = ['lat' => $lat, 'lng' => $lng, 'intensity' => $im, 'w' => $wm, 'w_moving' => $wm, 'w_stopped' => $ws];
            } elseif ($mode === 'parking' && $ws > 0) {
                $points[] = ['lat' => $lat, 'lng' => $lng, 'intensity' => $is, 'w' => $ws, 'w_moving' => $wm, 'w_stopped' => $ws];
            }
        }

        return [
            'map' => [
                'points' => $points,
                'buckets' => $bucketsOut,
                'mode' => $mode,
                'heatmap_motion' => self::heatmapMotionLabel($mode),
                'campaign_id' => $campaignId,
                'normalization' => $normalization,
                'heatmap_rollup' => false,
            ],
            'debug' => [
                'intensity_gamma' => $gamma,
                'intensity_stopped_power' => HeatmapIntensityNormalizer::STOPPED_INTENSITY_POWER,
                'cap_moving' => $capMoving,
                'cap_stopped' => $capStopped,
                'cap_total' => max($capMoving, $capStopped),
            ],
        ];
    }

    /**
     * @return array{map: array<string, mixed>, debug: array<string, mixed>}
     */
    private function emptyMapAndDebug(int $campaignId, string $mode, string $normalization): array
    {
        $gamma = TelemetryHeatmapConfig::intensityGamma();

        return [
            'map' => [
                'points' => [],
                'buckets' => [],
                'mode' => $mode,
                'heatmap_motion' => self::heatmapMotionLabel($mode),
                'campaign_id' => $campaignId,
                'normalization' => $normalization,
                'heatmap_rollup' => false,
            ],
            'debug' => [
                'intensity_gamma' => $gamma,
                'intensity_stopped_power' => HeatmapIntensityNormalizer::STOPPED_INTENSITY_POWER,
                'cap_moving' => 1,
                'cap_stopped' => 1,
                'cap_total' => 1,
            ],
        ];
    }

    private static function heatmapMotionLabel(string $mode): string
    {
        return match ($mode) {
            'parking' => 'stopped',
            default => 'moving',
        };
    }
}
