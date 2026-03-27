<?php

namespace App\Services\Reports;

use App\Services\Reports\Contracts\HeatmapImageServiceInterface;
use App\Services\Telemetry\HeatmapDataServiceInterface;
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
        string $absolutePath
    ): void {
        if (! in_array($mode, ['driving', 'parking'], true)) {
            $mode = 'driving';
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
            $heatData[] = [$p['lat'], $p['lng'], $p['intensity']];
        }

        $html = View::make('reports.heatmap-export', [
            'heatData' => $heatData,
            'modeLabel' => $mode === 'parking' ? 'Parking' : 'Driving',
            'periodLabel' => $dateFrom.' — '.$dateTo,
            'vehicleCount' => count($vehicleIds),
        ])->render();

        $w = (int) config('reports.heatmap_image.width', 1280);
        $h = (int) config('reports.heatmap_image.height', 720);
        $timeout = (int) config('reports.browsershot_timeout', 180);
        $delayMs = (int) config('reports.heatmap_render_delay_ms', 3500);

        // Без networkidle0: unpkg + тайлы карт часто не доходят до «idle» на сервере → таймаут.
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
