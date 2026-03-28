<?php

namespace App\Services\Reports;

/**
 * PDF parking heatmap: scale rollup sample totals (dwell proxy) with sqrt to spread mid-tones.
 * True parking minutes are not in heatmap_cells_daily yet; samples approximate relative dwell.
 */
final class ReportParkingHeatmapIntensityScaler
{
    /**
     * @param  list<array{lat: float, lng: float, w: int}>  $cells
     * @return list<array{0: float, 1: float, 2: float}>
     */
    public static function scaleFromSampleWeights(array $cells): array
    {
        if ($cells === []) {
            return [];
        }

        $maxW = 0;
        foreach ($cells as $c) {
            $maxW = max($maxW, (int) ($c['w'] ?? 0));
        }
        if ($maxW <= 0) {
            return [];
        }

        $out = [];
        foreach ($cells as $c) {
            $w = max(0, (int) ($c['w'] ?? 0));
            $t = sqrt(max(0.0, $w / $maxW));
            $out[] = [(float) $c['lat'], (float) $c['lng'], min(1.0, $t)];
        }

        return $out;
    }
}
