<?php

namespace App\Services\Reports;

use App\Services\Reports\Contracts\HeatmapImageServiceInterface;
use App\Services\Telemetry\HeatmapDataServiceInterface;
use App\Services\Telemetry\HeatmapLeafletStyle;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

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
        string $viewportId = 'full',
        ?array $parkingTopLocations = null,
    ): void {
        if (! in_array($mode, ['driving', 'parking'], true)) {
            $mode = 'driving';
        }

        $viewport = ReportHeatmapViewports::byId($viewportId) ?? ReportHeatmapViewports::all()[0];

        if ($mode === 'parking' && $parkingTopLocations !== null) {
            $this->renderParkingCirclesPng(
                $dateFrom,
                $dateTo,
                $vehicleIds,
                $absolutePath,
                $viewport,
                $parkingTopLocations
            );

            return;
        }

        $norm = (string) config('reports.normalization', 'max');
        if (! in_array($norm, ['max', 'p95', 'p99'], true)) {
            $norm = 'max';
        }

        $filters = [
            'vehicle_ids' => $vehicleIds,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'mode' => $mode,
            'normalization' => $norm,
        ];

        $bundle = $this->heatmapData->fetchHeatmapData($campaignId, $filters);
        $points = $bundle['map']['points'] ?? [];
        $filtered = ReportHeatmapExportPointFilter::filter($points);
        $heatData = [];
        foreach ($filtered as $p) {
            $heatData[] = [(float) $p['lat'], (float) $p['lng'], (float) $p['intensity']];
        }

        $intensityMode = strtolower((string) config('reports.heatmaps.driving.export_intensity_mode', 'log'));
        if ($mode === 'driving') {
            $heatData = ReportDrivingHeatmapIntensityScaler::scale($heatData, $intensityMode);
        }

        $html = View::make('reports.heatmap-export', [
            'exportMode' => 'driving_heat',
            'heatData' => $heatData,
            'parkingCircles' => [],
            'modeLabel' => $mode === 'parking' ? 'Parking' : 'Driving',
            'viewportLabel' => $viewport['label'],
            'periodLabel' => $dateFrom.' — '.$dateTo,
            'vehicleCount' => count($vehicleIds),
            'viewport' => $viewport,
            'tileLayer' => HeatmapLeafletStyle::tileLayerConfig(),
            'heatLayerOptions' => HeatmapLeafletStyle::heatLayerOptionsForExport($mode),
        ])->render();

        $this->captureHtmlToPng($html, $absolutePath);
    }

    /**
     * Parking report pages: circle markers from analytics top_locations; bounds = circles on this page
     * (after viewport bbox filter when using fixed frames).
     *
     * @param  list<array<string, mixed>>  $topLocations
     */
    private function renderParkingCirclesPng(
        string $dateFrom,
        string $dateTo,
        array $vehicleIds,
        string $absolutePath,
        array $viewport,
        array $topLocations
    ): void {
        $circles = ReportParkingCirclesExportBuilder::build($topLocations, $viewport);

        $html = View::make('reports.heatmap-export', [
            'exportMode' => 'parking_circles',
            'heatData' => [],
            'parkingCircles' => $circles,
            'modeLabel' => 'Parking',
            'viewportLabel' => $viewport['label'],
            'periodLabel' => $dateFrom.' — '.$dateTo,
            'vehicleCount' => count($vehicleIds),
            'viewport' => $viewport,
            'tileLayer' => HeatmapLeafletStyle::tileLayerConfig(),
            'heatLayerOptions' => [],
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
