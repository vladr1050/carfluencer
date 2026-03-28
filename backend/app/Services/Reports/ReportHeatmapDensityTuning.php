<?php

namespace App\Services\Reports;

/**
 * Adapts heat radius/blur from cell density (cells per square degree in query bbox).
 */
final class ReportHeatmapDensityTuning
{
    public static function adjustRadius(int $baseRadius, float $cellsPerDeg2): int
    {
        $high = (float) config('reports.heatmap_export.density_high_cells_per_deg2', 2500);
        $low = (float) config('reports.heatmap_export.density_low_cells_per_deg2', 200);
        $r = $baseRadius;
        if ($cellsPerDeg2 > $high) {
            $r -= 4;
        }
        if ($cellsPerDeg2 < $low) {
            $r += 4;
        }

        return max(6, min(48, $r));
    }

    public static function adjustBlur(int $baseBlur, float $cellsPerDeg2): int
    {
        $high = (float) config('reports.heatmap_export.density_high_cells_per_deg2', 2500);
        $low = (float) config('reports.heatmap_export.density_low_cells_per_deg2', 200);
        $b = $baseBlur;
        if ($cellsPerDeg2 > $high) {
            $b -= 3;
        }
        if ($cellsPerDeg2 < $low) {
            $b += 3;
        }

        return max(8, min(44, $b));
    }
}
