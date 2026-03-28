<?php

namespace App\Services\Reports;

/**
 * Bbox for reading heatmap_cells_daily during PDF/PNG export (rollup query).
 */
final class ReportHeatmapExportBBox
{
    /**
     * @param  array<string, mixed>  $viewport  {@see ReportHeatmapViewports}
     * @return array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}
     */
    public static function forRollup(array $viewport): array
    {
        $fit = filter_var($viewport['fit_to_data'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($fit) {
            /** @var array{south: float, north: float, west: float, east: float} $b */
            $b = config('reports.heatmap_export.bounds');

            return [
                'min_lat' => (float) $b['south'],
                'max_lat' => (float) $b['north'],
                'min_lng' => (float) $b['west'],
                'max_lng' => (float) $b['east'],
            ];
        }

        return [
            'min_lat' => (float) ($viewport['south'] ?? 0),
            'max_lat' => (float) ($viewport['north'] ?? 0),
            'min_lng' => (float) ($viewport['west'] ?? 0),
            'max_lng' => (float) ($viewport['east'] ?? 0),
        ];
    }

    /**
     * @param  array{min_lat: float, max_lat: float, min_lng: float, max_lng: float}  $bbox
     */
    public static function areaDeg2(array $bbox): float
    {
        $latSpan = $bbox['max_lat'] - $bbox['min_lat'];
        $lngSpan = $bbox['max_lng'] - $bbox['min_lng'];

        return max(1e-12, $latSpan * $lngSpan);
    }
}
