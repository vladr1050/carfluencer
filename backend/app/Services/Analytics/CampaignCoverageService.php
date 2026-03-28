<?php

namespace App\Services\Analytics;

use App\Services\Telemetry\HeatmapAggregationService;
use App\Services\Telemetry\HeatmapBucketStrategy;
use Illuminate\Support\Facades\DB;

/**
 * Driving footprint from heatmap_cells_daily: distinct cells vs a fixed reference grid
 * over {@see config('reports.heatmap_export.bounds')} (operational export envelope).
 *
 * Config {@see config('reports.coverage.map_zoom')} is a Leaflet-style map zoom; it is
 * mapped to DB {@see HeatmapBucketStrategy::tierFromMapZoom} — not a raw zoom_tier index.
 */
final class CampaignCoverageService
{
    /**
     * @param  list<string>  $deviceImeis
     * @return array{
     *     unique_cells: int,
     *     reference_cells: int,
     *     coverage_ratio: float,
     *     coverage_pattern: string|null,
     *     method: string,
     *     denominator_scope: string,
     *     rollup_tier_index: int,
     *     map_zoom_used: int
     * }
     */
    public function buildCoverage(
        string $dateFrom,
        string $dateTo,
        array $deviceImeis,
        int $mapZoom
    ): array {
        $mapZoom = max(1, min(22, $mapZoom));
        $tier = HeatmapBucketStrategy::tierFromMapZoom($mapZoom);
        $decimals = HeatmapBucketStrategy::decimalPlacesForTier($tier);

        /** @var array{south?: float, north?: float, west?: float, east?: float} $bounds */
        $bounds = config('reports.heatmap_export.bounds', []);
        $south = (float) ($bounds['south'] ?? 0.0);
        $north = (float) ($bounds['north'] ?? 0.0);
        $west = (float) ($bounds['west'] ?? 0.0);
        $east = (float) ($bounds['east'] ?? 0.0);

        $referenceCells = CoverageReferenceGrid::referenceCellCountInBBox(
            $south,
            $north,
            $west,
            $east,
            $decimals
        );

        $uniqueCells = $deviceImeis === []
            ? 0
            : $this->countDistinctDrivingCells($dateFrom, $dateTo, $tier, $deviceImeis);

        $ratio = 0.0;
        if ($referenceCells > 0) {
            $ratio = min(1.0, $uniqueCells / $referenceCells);
        }

        $pattern = $this->classifyCoveragePattern($uniqueCells, $ratio);

        return [
            'unique_cells' => $uniqueCells,
            'reference_cells' => $referenceCells,
            'coverage_ratio' => round($ratio, 4),
            'coverage_pattern' => $pattern,
            'method' => 'spatial_rollup_driving_cells',
            'denominator_scope' => 'operational_bounds_grid',
            'rollup_tier_index' => $tier,
            'map_zoom_used' => $mapZoom,
        ];
    }

    /**
     * @param  list<string>  $deviceImeis
     */
    private function countDistinctDrivingCells(
        string $dateFrom,
        string $dateTo,
        int $tier,
        array $deviceImeis
    ): int {
        $sub = DB::table('heatmap_cells_daily')
            ->whereBetween('day', [$dateFrom, $dateTo])
            ->where('mode', HeatmapAggregationService::MODE_DRIVING)
            ->where('zoom_tier', $tier)
            ->whereIn('device_id', $deviceImeis)
            ->select('lat_bucket', 'lng_bucket')
            ->distinct();

        return (int) DB::query()->fromSub($sub, 'distinct_cells')->count();
    }

    /**
     * Interpretation: ratio <= focused_max → focused; <= balanced_max → balanced; else wide.
     * No observed driving cells → pattern null (ratio alone is not meaningful).
     */
    private function classifyCoveragePattern(int $uniqueCells, float $ratio): ?string
    {
        if ($uniqueCells <= 0) {
            return null;
        }

        $focusedMax = (float) config('reports.coverage.patterns.focused_max', 0.20);
        $balancedMax = (float) config('reports.coverage.patterns.balanced_max', 0.50);

        if ($ratio <= $focusedMax) {
            return 'focused';
        }
        if ($ratio <= $balancedMax) {
            return 'balanced';
        }

        return 'wide';
    }
}
