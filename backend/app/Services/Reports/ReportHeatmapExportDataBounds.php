<?php

namespace App\Services\Reports;

/**
 * Tight Leaflet fitBounds for PDF heatmap PNG: bbox from active heat points (rollup cells),
 * padded and clamped to the viewport envelope; falls back to legacy fitting when spread is huge or too few points.
 *
 * Visual leaflet.heat glow (radius + blur in screen pixels) is kept inside the PNG via
 * {@see resources/views/reports/heatmap-export.blade.php} fitBounds pixel padding, derived from the same
 * {@see \App\Services\Telemetry\HeatmapLeafletStyle::heatLayerOptionsForExport} as the layer.
 */
final class ReportHeatmapExportDataBounds
{
    /**
     * @param  list<array{0: float, 1: float, 2: float}>  $heatData  [lat, lng, intensity] from export pipeline
     * @param  array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}  $queryEnvelope  bbox used for rollup fetch (viewport / full bounds)
     * @return array{
     *     use_data_fit: bool,
     *     south?: float,
     *     north?: float,
     *     west?: float,
     *     east?: float,
     *     max_zoom: int
     * }
     */
    public static function compute(array $heatData, array $queryEnvelope): array
    {
        $tileCap = 19;
        $cfgMax = (int) config('reports.heatmap_export.leaflet_fit_max_zoom', 14);
        $maxZoom = max(1, min($tileCap, $cfgMax));

        if (! filter_var(config('reports.heatmap_export.data_fit_to_active_cells', true), FILTER_VALIDATE_BOOLEAN)) {
            return ['use_data_fit' => false, 'max_zoom' => $maxZoom];
        }

        $minPts = max(1, (int) config('reports.heatmap_export.data_fit_min_points', 2));
        if (count($heatData) < $minPts) {
            return ['use_data_fit' => false, 'max_zoom' => $maxZoom];
        }

        $lats = [];
        $lngs = [];
        foreach ($heatData as $t) {
            $lats[] = (float) $t[0];
            $lngs[] = (float) $t[1];
        }

        $minLat = min($lats);
        $maxLat = max($lats);
        $minLng = min($lngs);
        $maxLng = max($lngs);

        $latSpan = $maxLat - $minLat;
        $lngSpan = $maxLng - $minLng;

        $maxLatSpan = (float) config('reports.heatmap_export.data_fit_max_lat_span_deg', 0.45);
        $maxLngSpan = (float) config('reports.heatmap_export.data_fit_max_lng_span_deg', 0.7);
        if ($latSpan > $maxLatSpan || $lngSpan > $maxLngSpan) {
            return ['use_data_fit' => false, 'max_zoom' => $maxZoom];
        }

        $minLatS = (float) config('reports.heatmap_export.data_fit_min_lat_span_deg', 0.012);
        $minLngS = (float) config('reports.heatmap_export.data_fit_min_lng_span_deg', 0.018);
        if ($latSpan < $minLatS) {
            $mid = ($minLat + $maxLat) / 2.0;
            $minLat = $mid - $minLatS / 2.0;
            $maxLat = $mid + $minLatS / 2.0;
            $latSpan = $minLatS;
        }
        if ($lngSpan < $minLngS) {
            $mid = ($minLng + $maxLng) / 2.0;
            $minLng = $mid - $minLngS / 2.0;
            $maxLng = $mid + $minLngS / 2.0;
            $lngSpan = $minLngS;
        }

        $pad = (float) config('reports.heatmap_export.data_fit_padding_ratio', 0.12);
        $pad = max(0.0, min(0.25, $pad));
        $latPad = max(1e-8, $latSpan * $pad);
        $lngPad = max(1e-8, $lngSpan * $pad);

        $south = $minLat - $latPad;
        $north = $maxLat + $latPad;
        $west = $minLng - $lngPad;
        $east = $maxLng + $lngPad;

        $south = max($queryEnvelope['min_lat'], $south);
        $north = min($queryEnvelope['max_lat'], $north);
        $west = max($queryEnvelope['min_lng'], $west);
        $east = min($queryEnvelope['max_lng'], $east);

        if ($south >= $north || $west >= $east) {
            return ['use_data_fit' => false, 'max_zoom' => $maxZoom];
        }

        return [
            'use_data_fit' => true,
            'south' => $south,
            'north' => $north,
            'west' => $west,
            'east' => $east,
            'max_zoom' => $maxZoom,
        ];
    }
}
