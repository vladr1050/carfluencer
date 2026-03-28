<?php

namespace App\Services\Reports;

use App\Models\Campaign;
use App\Models\Vehicle;
use App\Services\Reports\HeatmapExport\DrivingHeatmapProfile;
use App\Services\Reports\HeatmapExport\ParkingHeatmapProfile;
use App\Services\Telemetry\HeatmapAggregationService;
use App\Services\Telemetry\HeatmapRollupQueryService;

/**
 * PDF/PNG heatmap: always reads {@see heatmap_cells_daily} with a stable map zoom + export bbox.
 */
final class ReportHeatmapRollupExportBuilder
{
    public function __construct(
        private readonly HeatmapRollupQueryService $rollupQuery,
    ) {}

    /**
     * @param  list<int>  $vehicleIds
     * @param  array<string, mixed>  $viewport
     * @param  list<array<string, mixed>>|null  $parkingTopLocations
     * @return array{
     *     heatData: list<array{0: float, 1: float, 2: float}>,
     *     heatLayerOptions: array<string, mixed>,
     *     hotspots: list<array{lat: float, lng: float, title: string, subtitle: string}>
     * }
     */
    public function build(
        int $campaignId,
        string $dateFrom,
        string $dateTo,
        array $vehicleIds,
        string $mode,
        array $viewport,
        ?array $parkingTopLocations = null,
    ): array {
        if (! in_array($mode, [HeatmapAggregationService::MODE_DRIVING, HeatmapAggregationService::MODE_PARKING], true)) {
            $mode = HeatmapAggregationService::MODE_DRIVING;
        }

        $imeis = $this->resolveDeviceIds($campaignId, $vehicleIds);
        if ($imeis === []) {
            return [
                'heatData' => [],
                'heatLayerOptions' => $mode === HeatmapAggregationService::MODE_PARKING
                    ? ParkingHeatmapProfile::heatLayerOptions(
                        (int) config('reports.heatmaps.parking.radius', 26),
                        (int) config('reports.heatmaps.parking.blur', 30)
                    )
                    : DrivingHeatmapProfile::heatLayerOptions(
                        (int) config('reports.heatmaps.driving.radius', 14),
                        (int) config('reports.heatmaps.driving.blur', 24)
                    ),
                'hotspots' => [],
            ];
        }

        $bbox = ReportHeatmapExportBBox::forRollup($viewport);
        $mapZoom = (int) config('reports.heatmap_export.rollup_read_zoom', 12);
        $maxCells = (int) config('reports.heatmap_export.max_cells', 50000);
        $maxCells = max(1000, min(200000, $maxCells));

        $cells = $this->rollupQuery->fetchAggregatedSampleSums($imeis, $dateFrom, $dateTo, $mode, $mapZoom, $bbox);
        $cells = ReportHeatmapExportPointFilter::filterWeightedCells($cells);

        usort($cells, static fn (array $a, array $b): int => $b['w'] <=> $a['w']);
        $topForLabels = array_slice($cells, 0, 5);
        $cells = self::capCellCount($cells, $maxCells);

        $area = ReportHeatmapExportBBox::areaDeg2($bbox);
        $density = count($cells) / $area;

        if ($mode === HeatmapAggregationService::MODE_PARKING) {
            $baseR = (int) config('reports.heatmaps.parking.radius', 26);
            $baseB = (int) config('reports.heatmaps.parking.blur', 30);
            $radius = ReportHeatmapDensityTuning::adjustRadius($baseR, $density);
            $blur = ReportHeatmapDensityTuning::adjustBlur($baseB, $density);
            $heatData = ReportParkingHeatmapIntensityScaler::scaleFromSampleWeights($cells);
            $options = ParkingHeatmapProfile::heatLayerOptions($radius, $blur);
        } else {
            $baseR = (int) config('reports.heatmaps.driving.radius', 14);
            $baseB = (int) config('reports.heatmaps.driving.blur', 24);
            $radius = ReportHeatmapDensityTuning::adjustRadius($baseR, $density);
            $blur = ReportHeatmapDensityTuning::adjustBlur($baseB, $density);
            $intensityMode = strtolower((string) config('reports.heatmaps.driving.export_intensity_mode', 'log'));
            $heatData = ReportDrivingHeatmapIntensityScaler::scaleFromSampleWeights($cells, $intensityMode);
            $options = DrivingHeatmapProfile::heatLayerOptions($radius, $blur);
        }

        $hotspots = ReportHeatmapHotspotOverlay::build($mode, $topForLabels, $parkingTopLocations, $viewport);

        return [
            'heatData' => $heatData,
            'heatLayerOptions' => $options,
            'hotspots' => $hotspots,
        ];
    }

    /**
     * @param  list<int>  $vehicleIds
     * @return list<string>
     */
    private function resolveDeviceIds(int $campaignId, array $vehicleIds): array
    {
        $ids = Campaign::query()
            ->findOrFail($campaignId)
            ->campaignVehicles()
            ->pluck('vehicle_id');

        if ($vehicleIds !== []) {
            $allowed = array_map('intval', $vehicleIds);
            $ids = $ids->filter(fn (int $id) => in_array($id, $allowed, true));
        }

        $ids = $ids->values()->all();

        return Vehicle::query()
            ->whereIn('id', $ids)
            ->pluck('imei')
            ->filter()
            ->map(fn ($i) => (string) $i)
            ->values()
            ->all();
    }

    /**
     * @param  list<array{lat: float, lng: float, w: int}>  $cells  sorted by weight desc
     * @return list<array{lat: float, lng: float, w: int}>
     */
    private static function capCellCount(array $cells, int $max): array
    {
        if (count($cells) <= $max) {
            return $cells;
        }

        return array_slice($cells, 0, $max);
    }
}
