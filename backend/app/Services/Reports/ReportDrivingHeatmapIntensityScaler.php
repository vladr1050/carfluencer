<?php

namespace App\Services\Reports;

/**
 * Export-only visual scaling for report driving heatmap PNGs (third coordinate for L.heatLayer).
 * Does not alter API/rollup; applied after {@see ReportHeatmapExportPointFilter}.
 *
 * - linear: pass through intensities unchanged (already in [0..1] from pipeline).
 * - log: log1p remap across the filtered export set using max third coordinate.
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

        $maxValue = 0.0;
        foreach ($heatData as $row) {
            $v = (float) ($row[2] ?? 0.0);
            if ($v > $maxValue) {
                $maxValue = $v;
            }
        }

        if ($maxValue <= 0.0) {
            $out = [];
            foreach ($heatData as $row) {
                $out[] = [(float) $row[0], (float) $row[1], 0.0];
            }

            return $out;
        }

        $denom = log($maxValue + 1.0);
        if ($denom <= 0.0) {
            return $heatData;
        }

        $out = [];
        foreach ($heatData as $row) {
            $v = max(0.0, (float) ($row[2] ?? 0.0));
            $scaled = log($v + 1.0) / $denom;
            $out[] = [(float) $row[0], (float) $row[1], max(0.0, min(1.0, $scaled))];
        }

        return $out;
    }
}
