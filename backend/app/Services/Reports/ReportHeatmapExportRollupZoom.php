<?php

namespace App\Services\Reports;

/**
 * Leaflet zoom passed into heatmap rollup reads controls {@see HeatmapBucketStrategy::tierFromMapZoom}.
 * A tight PDF viewport (e.g. Rīga centrs) must use a higher zoom than regional frames, otherwise
 * coarse buckets look like separate blobs on a zoomed-in map.
 */
final class ReportHeatmapExportRollupZoom
{
    /**
     * @param  array<string, mixed>  $viewport
     * @param  array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}  $bbox
     */
    public static function forViewport(array $viewport, array $bbox): int
    {
        $base = (int) config('reports.heatmap_export.rollup_read_zoom', 12);
        $base = max(1, min(22, $base));

        if (filter_var($viewport['fit_to_data'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return $base;
        }

        $latSpan = abs($bbox['max_lat'] - $bbox['min_lat']);
        $lngSpan = abs($bbox['max_lng'] - $bbox['min_lng']);
        $span = max($latSpan, $lngSpan);

        /*
         * Approximate mapping (Baltic lat ~57°): 0.1° ≈ 8–11 km edge. Below that, bump zoom so
         * tier uses more decimals (finer cells). Never below base — operator may set a high default.
         */
        if ($span <= 0.12) {
            return max($base, 15);
        }
        if ($span <= 0.35) {
            return max($base, 14);
        }

        return $base;
    }
}
