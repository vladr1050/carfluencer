<?php

namespace App\Services\Reports;

/**
 * Export-only visual scaling for report driving heatmap PNGs (third coordinate for L.heatLayer).
 * Does not alter API/rollup; applied to raw SUM(samples_count) per cell from heatmap_cells_daily.
 *
 * - log: log(1+w)/log(1+max), capped at 0.85 so the palette keeps headroom (less solid red).
 * - linear: w/max in [0,1], same cap.
 */
final class ReportDrivingHeatmapIntensityScaler
{
    /**
     * @param  list<array{0: float, 1: float, 2: float}>  $heatData  [lat, lng, intensity]
     * @return list<array{0: float, 1: float, 2: float}>
     */
    public static function scale(array $heatData, string $mode): array
    {
        $mode = strtolower($mode);
        if ($mode !== 'log') {
            return $heatData;
        }

        if ($heatData === []) {
            return [];
        }

        $cells = [];
        foreach ($heatData as $row) {
            $cells[] = [
                'lat' => (float) ($row[0] ?? 0),
                'lng' => (float) ($row[1] ?? 0),
                'w' => (int) round(max(0.0, (float) ($row[2] ?? 0.0))),
            ];
        }

        return self::scaleFromSampleWeights($cells, 'log');
    }

    /**
     * @param  list<array{lat: float, lng: float, w: int}>  $cells
     * @return list<array{0: float, 1: float, 2: float}>
     */
    public static function scaleFromSampleWeights(array $cells, string $mode = 'log'): array
    {
        $mode = strtolower($mode);
        if ($cells === []) {
            return [];
        }

        $maxW = 0;
        foreach ($cells as $c) {
            $maxW = max($maxW, (int) ($c['w'] ?? 0));
        }
        if ($maxW <= 0) {
            $out = [];
            foreach ($cells as $c) {
                $out[] = [(float) $c['lat'], (float) $c['lng'], 0.0];
            }

            return $out;
        }

        if ($mode === 'linear') {
            $out = [];
            foreach ($cells as $c) {
                $w = max(0, (int) ($c['w'] ?? 0));
                $t = $w / $maxW;
                $t = min(0.85, $t);
                $out[] = [(float) $c['lat'], (float) $c['lng'], $t];
            }

            return $out;
        }

        $denom = log($maxW + 1.0);
        if ($denom <= 0.0) {
            $denom = 1.0;
        }

        $out = [];
        foreach ($cells as $c) {
            $w = max(0, (int) ($c['w'] ?? 0));
            $t = log($w + 1.0) / $denom;
            $t = min(0.85, $t);
            $out[] = [(float) $c['lat'], (float) $c['lng'], max(0.0, min(1.0, $t))];
        }

        return $out;
    }
}
