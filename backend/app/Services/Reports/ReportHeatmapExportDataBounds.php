<?php

namespace App\Services\Reports;

/**
 * PDF heatmap PNG framing: composition-first bbox from activity mass, then pad, clamp to envelope.
 *
 * Raw cell min/max often includes sparse tails; intensity-weighted cumulative percentiles approximate
 * “visual mass” for tighter framing (~85–95% fill) while leaflet.heat glow stays inside the frame via
 * {@see resources/views/reports/heatmap-export.blade.php} fitBounds pixel padding.
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
        $cfgMax = (int) config('reports.heatmap_export.leaflet_fit_max_zoom', 15);
        $maxZoom = max(1, min($tileCap, $cfgMax));

        if (! filter_var(config('reports.heatmap_export.data_fit_to_active_cells', true), FILTER_VALIDATE_BOOLEAN)) {
            return ['use_data_fit' => false, 'max_zoom' => $maxZoom];
        }

        $minPts = max(1, (int) config('reports.heatmap_export.data_fit_min_points', 2));
        if (count($heatData) < $minPts) {
            return ['use_data_fit' => false, 'max_zoom' => $maxZoom];
        }

        $composition = filter_var(config('reports.heatmap_export.data_fit_composition_enabled', true), FILTER_VALIDATE_BOOLEAN);
        $compMinPts = max($minPts, (int) config('reports.heatmap_export.data_fit_composition_min_points', 10));

        if ($composition && count($heatData) >= $compMinPts) {
            $lowFrac = (float) config('reports.heatmap_export.data_fit_composition_mass_low_frac', 0.07);
            $highFrac = (float) config('reports.heatmap_export.data_fit_composition_mass_high_frac', 0.93);
            $lowFrac = max(0.0, min(0.49, $lowFrac));
            $highFrac = max(0.51, min(1.0, $highFrac));
            if ($highFrac <= $lowFrac) {
                $highFrac = min(1.0, $lowFrac + 0.5);
            }

            $latExt = self::massPercentileExtent($heatData, 0, $lowFrac, $highFrac);
            $lngExt = self::massPercentileExtent($heatData, 1, $lowFrac, $highFrac);
            if ($latExt !== null && $lngExt !== null) {
                $minLat = $latExt['min'];
                $maxLat = $latExt['max'];
                $minLng = $lngExt['min'];
                $maxLng = $lngExt['max'];
            } else {
                $composition = false;
            }
        }

        if (! $composition || count($heatData) < $compMinPts) {
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
        }

        $latSpan = $maxLat - $minLat;
        $lngSpan = $maxLng - $minLng;

        $maxLatSpan = (float) config('reports.heatmap_export.data_fit_max_lat_span_deg', 0.45);
        $maxLngSpan = (float) config('reports.heatmap_export.data_fit_max_lng_span_deg', 0.7);
        if ($latSpan > $maxLatSpan || $lngSpan > $maxLngSpan) {
            return ['use_data_fit' => false, 'max_zoom' => $maxZoom];
        }

        $useCompositionSpan = $composition && count($heatData) >= $compMinPts;

        if ($useCompositionSpan) {
            $floorLat = (float) config('reports.heatmap_export.data_fit_composition_floor_lat_span_deg', 0.002);
            $floorLng = (float) config('reports.heatmap_export.data_fit_composition_floor_lng_span_deg', 0.003);
            $floorLat = max(1e-6, min(0.05, $floorLat));
            $floorLng = max(1e-6, min(0.05, $floorLng));
            if ($latSpan < $floorLat) {
                $mid = ($minLat + $maxLat) / 2.0;
                $minLat = $mid - $floorLat / 2.0;
                $maxLat = $mid + $floorLat / 2.0;
                $latSpan = $floorLat;
            }
            if ($lngSpan < $floorLng) {
                $mid = ($minLng + $maxLng) / 2.0;
                $minLng = $mid - $floorLng / 2.0;
                $maxLng = $mid + $floorLng / 2.0;
                $lngSpan = $floorLng;
            }
        } else {
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
        }

        if ($useCompositionSpan) {
            $pad = (float) config('reports.heatmap_export.data_fit_composition_pad_ratio', 0.07);
        } else {
            $pad = (float) config('reports.heatmap_export.data_fit_padding_ratio', 0.14);
        }
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

    /**
     * Intensity-weighted cumulative mass percentiles on one axis (lat or lng).
     *
     * @param  list<array{0: float, 1: float, 2: float}>  $heatData
     * @param  0|1  $axis
     * @return array{min: float, max: float}|null
     */
    private static function massPercentileExtent(array $heatData, int $axis, float $lowFrac, float $highFrac): ?array
    {
        $rows = [];
        foreach ($heatData as $t) {
            $coord = (float) $t[$axis];
            $w = max(1e-9, (float) $t[2]);
            $rows[] = ['c' => $coord, 'w' => $w];
        }
        usort($rows, static fn (array $a, array $b): int => $a['c'] <=> $b['c']);

        $total = 0.0;
        foreach ($rows as $r) {
            $total += $r['w'];
        }
        if ($total <= 0.0) {
            return null;
        }

        $targetLow = $total * $lowFrac;
        $targetHigh = $total * $highFrac;

        $cum = 0.0;
        $minCoord = null;
        foreach ($rows as $r) {
            $cum += $r['w'];
            if ($cum >= $targetLow) {
                $minCoord = $r['c'];
                break;
            }
        }

        $cum = 0.0;
        $maxCoord = null;
        foreach ($rows as $r) {
            $cum += $r['w'];
            if ($cum >= $targetHigh) {
                $maxCoord = $r['c'];
                break;
            }
        }

        if ($minCoord === null || $maxCoord === null || $maxCoord <= $minCoord) {
            return null;
        }

        return ['min' => $minCoord, 'max' => $maxCoord];
    }
}
