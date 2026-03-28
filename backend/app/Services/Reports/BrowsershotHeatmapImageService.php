<?php

namespace App\Services\Reports;

use App\Services\Reports\Contracts\HeatmapImageServiceInterface;
use App\Services\Telemetry\HeatmapLeafletStyle;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

final class BrowsershotHeatmapImageService implements HeatmapImageServiceInterface
{
    public function __construct(
        private readonly ReportHeatmapRollupExportBuilder $rollupExportBuilder,
    ) {}

    public function renderPng(
        int $campaignId,
        string $dateFrom,
        string $dateTo,
        array $vehicleIds,
        string $mode,
        string $absolutePath,
        string $viewportId = 'full',
        ?array $parkingTopLocations = null,
    ): void {
        if (! in_array($mode, ['driving', 'parking'], true)) {
            $mode = 'driving';
        }

        $viewport = ReportHeatmapViewports::byId($viewportId) ?? ReportHeatmapViewports::all()[0];

        $built = $this->rollupExportBuilder->build(
            $campaignId,
            $dateFrom,
            $dateTo,
            $vehicleIds,
            $mode,
            $viewport,
            $parkingTopLocations
        );

        $legendVariant = $mode === 'parking' ? 'parking_heat' : 'driving_heat';

        $html = View::make('reports.heatmap-export', [
            'exportMode' => 'heatmap',
            'legendVariant' => $legendVariant,
            'heatData' => $built['heatData'],
            'hotspots' => $built['hotspots'],
            'modeLabel' => $mode === 'parking' ? 'Parking' : 'Driving',
            'viewportLabel' => $viewport['label'],
            'periodLabel' => $dateFrom.' — '.$dateTo,
            'vehicleCount' => count($vehicleIds),
            'viewport' => $viewport,
            'tileLayer' => HeatmapLeafletStyle::tileLayerConfig(),
            'heatLayerOptions' => $built['heatLayerOptions'],
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
