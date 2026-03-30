<?php

namespace App\Services\Reports;

use App\Services\Reports\Contracts\HeatmapImageServiceInterface;
use App\Services\Telemetry\HeatmapDataServiceInterface;
use App\Services\Telemetry\HeatmapLeafletStyle;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

/**
 * PNG heatmaps for PDF: same API payload and Leaflet.heat options as the Advertiser portal
 * ({@see HeatmapLeafletStyle::heatLayerOptionsForExport} + {@see HeatmapDataServiceInterface::fetchHeatmapData}).
 */
final class BrowsershotHeatmapImageService implements HeatmapImageServiceInterface
{
    public function __construct(
        private readonly HeatmapDataServiceInterface $heatmapData,
    ) {}

    public function renderPng(
        int $campaignId,
        string $dateFrom,
        string $dateTo,
        array $vehicleIds,
        string $mode,
        string $absolutePath,
        string $viewportId = 'baltics',
        ?array $parkingTopLocations = null,
    ): void {
        if (! in_array($mode, ['driving', 'parking'], true)) {
            $mode = 'driving';
        }

        $viewport = ReportHeatmapViewports::byId($viewportId) ?? ReportHeatmapViewports::all()[0];

        $frameBbox = ReportHeatmapExportBBox::forRollup($viewport);
        $zoom = ReportHeatmapExportRollupZoom::forViewport($viewport, $frameBbox);
        $zoom = max(1, min(22, $zoom));

        $queryBbox = filter_var(config('reports.heatmap_export.pdf_rollup_query_full_operational_bounds', true), FILTER_VALIDATE_BOOLEAN)
            ? ReportHeatmapExportBBox::operationalEnvelope()
            : $frameBbox;

        $norm = (string) config('reports.normalization', 'p95');
        if (! in_array($norm, ['max', 'p95', 'p99'], true)) {
            $norm = 'p95';
        }

        $maxCells = max(1000, (int) config('reports.heatmap_export.max_cells', 50_000));

        $filters = [
            'vehicle_ids' => $vehicleIds,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'mode' => $mode,
            'normalization' => $norm,
            'south' => $queryBbox['min_lat'],
            'west' => $queryBbox['min_lng'],
            'north' => $queryBbox['max_lat'],
            'east' => $queryBbox['max_lng'],
            'zoom' => $zoom,
            'max_rollup_cells' => $maxCells,
        ];

        $bundle = $this->heatmapData->fetchHeatmapData($campaignId, $filters);
        $points = $bundle['map']['points'] ?? [];
        $filtered = ReportHeatmapExportPointFilter::filter($points);

        $heatData = [];
        foreach ($filtered as $p) {
            $heatData[] = [(float) $p['lat'], (float) $p['lng'], (float) $p['intensity']];
        }

        $mapFit = ReportHeatmapExportDataBounds::compute($heatData, $queryBbox);

        $legendVariant = $mode === 'parking' ? 'parking_heat' : 'driving_heat';

        $html = View::make('reports.heatmap-export', [
            'exportMode' => 'heatmap',
            'legendVariant' => $legendVariant,
            'heatData' => $heatData,
            'modeLabel' => $mode === 'parking' ? 'Parking' : 'Driving',
            'viewportLabel' => $viewport['label'],
            'periodLabel' => $dateFrom.' — '.$dateTo,
            'vehicleCount' => count($vehicleIds),
            'viewport' => $viewport,
            'mapFit' => $mapFit,
            'tileLayer' => HeatmapLeafletStyle::tileLayerConfig(),
            'heatLayerOptions' => HeatmapLeafletStyle::heatLayerOptionsForExport($mode),
        ])->render();

        $this->captureHtmlToPng($html, $absolutePath);
    }

    private function captureHtmlToPng(string $html, string $absolutePath): void
    {
        $w = (int) config('reports.heatmap_image.width', 1280);
        $h = (int) config('reports.heatmap_image.height', 720);
        $timeout = (int) config('reports.browsershot_timeout', 180);
        $delayMs = (int) config('reports.heatmap_render_delay_ms', 3500);

        $shot = Browsershot::html($html)
            ->windowSize($w, $h)
            ->deviceScaleFactor(1)
            ->setScreenshotType('png')
            ->timeout(max(30, $timeout))
            ->delay(max(0, $delayMs));

        BrowsershotConfigurator::apply($shot);

        $shot->save($absolutePath);
    }
}
