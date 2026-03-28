<?php

namespace App\Services\Telemetry;

use Illuminate\Support\Facades\DB;

/**
 * Read path: SUM samples across days/devices into map buckets, then normalize with {@see HeatmapIntensityNormalizer}.
 */
final class HeatmapRollupQueryService
{
    /**
     * @param  list<string>  $deviceIds  IMEIs
     * @param  array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}  $bbox
     * @return list<array{lat: float, lng: float, w: int, intensity: float}>
     */
    public function fetchBuckets(
        array $deviceIds,
        string $dateFrom,
        string $dateTo,
        string $mode,
        int $mapZoom,
        array $bbox,
        string $normalization = 'p95'
    ): array {
        if ($deviceIds === []) {
            return [];
        }

        if (! in_array($mode, [HeatmapAggregationService::MODE_DRIVING, HeatmapAggregationService::MODE_PARKING], true)) {
            throw new \InvalidArgumentException('mode must be driving or parking');
        }

        if (! in_array($normalization, ['max', 'p95', 'p99'], true)) {
            $normalization = 'p95';
        }

        $tier = HeatmapBucketStrategy::tierFromMapZoom($mapZoom);

        $driver = DB::getDriverName();
        $sumExpr = $driver === 'pgsql'
            ? 'lat_bucket, lng_bucket, SUM(samples_count)::bigint AS cnt'
            : 'lat_bucket, lng_bucket, CAST(SUM(samples_count) AS INTEGER) AS cnt';

        $rows = DB::table('heatmap_cells_daily')
            ->selectRaw($sumExpr)
            ->whereBetween('day', [$dateFrom, $dateTo])
            ->where('mode', $mode)
            ->where('zoom_tier', $tier)
            ->whereIn('device_id', $deviceIds)
            ->whereBetween('lat_bucket', [(float) $bbox['min_lat'], (float) $bbox['max_lat']])
            ->whereBetween('lng_bucket', [(float) $bbox['min_lng'], (float) $bbox['max_lng']])
            ->groupBy('lat_bucket', 'lng_bucket')
            ->havingRaw('SUM(samples_count) > 0')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $weights = $rows->map(fn ($r) => (int) $r->cnt)->all();
        $gamma = TelemetryHeatmapConfig::intensityGamma();
        $cap = $mode === HeatmapAggregationService::MODE_PARKING
            ? HeatmapIntensityNormalizer::capFromWeights($weights, $normalization)
            : HeatmapIntensityNormalizer::capFromWeights($weights, $normalization);

        $out = [];
        foreach ($rows as $r) {
            $w = (int) $r->cnt;
            $lat = (float) $r->lat_bucket;
            $lng = (float) $r->lng_bucket;
            $intensity = $mode === HeatmapAggregationService::MODE_PARKING
                ? HeatmapIntensityNormalizer::normalizeStopped($w, $cap)
                : HeatmapIntensityNormalizer::normalize($w, $cap, $gamma);

            $out[] = [
                'lat' => $lat,
                'lng' => $lng,
                'w' => $w,
                'intensity' => $intensity,
            ];
        }

        return $out;
    }

    /**
     * Total samples in rollup for metrics (same filters, no bbox).
     *
     * @param  list<string>  $deviceIds
     */
    public function sumSamplesInRange(
        array $deviceIds,
        string $dateFrom,
        string $dateTo,
        string $mode,
        int $mapZoom
    ): int {
        if ($deviceIds === []) {
            return 0;
        }

        $tier = HeatmapBucketStrategy::tierFromMapZoom($mapZoom);

        return (int) DB::table('heatmap_cells_daily')
            ->whereBetween('day', [$dateFrom, $dateTo])
            ->where('mode', $mode)
            ->where('zoom_tier', $tier)
            ->whereIn('device_id', $deviceIds)
            ->sum('samples_count');
    }

    /**
     * Top parking cells by SUM(samples_count), no bbox (MVP: no clustering).
     * {@see HeatmapBucketStrategy::tierFromMapZoom} must match rollup write/read tier.
     *
     * @param  list<string>  $deviceIds  IMEIs (heatmap_cells_daily.device_id)
     * @return list<array{lat: float, lng: float, samples: int}>
     */
    public function fetchTopParkingBySamples(
        array $deviceIds,
        string $dateFrom,
        string $dateTo,
        int $mapZoom,
        int $limit = 10
    ): array {
        if ($deviceIds === [] || $limit <= 0) {
            return [];
        }

        $tier = HeatmapBucketStrategy::tierFromMapZoom($mapZoom);

        $driver = DB::getDriverName();
        $sumExpr = $driver === 'pgsql'
            ? 'lat_bucket, lng_bucket, SUM(samples_count)::bigint AS cnt'
            : 'lat_bucket, lng_bucket, CAST(SUM(samples_count) AS INTEGER) AS cnt';

        $rows = DB::table('heatmap_cells_daily')
            ->selectRaw($sumExpr)
            ->whereBetween('day', [$dateFrom, $dateTo])
            ->where('mode', HeatmapAggregationService::MODE_PARKING)
            ->where('zoom_tier', $tier)
            ->whereIn('device_id', $deviceIds)
            ->groupBy('lat_bucket', 'lng_bucket')
            ->havingRaw('SUM(samples_count) > 0')
            ->orderByDesc(DB::raw('SUM(samples_count)'))
            ->limit($limit)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'lat' => round((float) $r->lat_bucket, 6),
                'lng' => round((float) $r->lng_bucket, 6),
                'samples' => (int) $r->cnt,
            ];
        }

        return $out;
    }
}
