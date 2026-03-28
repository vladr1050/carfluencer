<?php

namespace App\Services\Reports;

use App\Services\Reports\Contracts\CampaignReportPdfServiceInterface;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

final class BrowsershotCampaignReportPdfService implements CampaignReportPdfServiceInterface
{
    public function renderPdf(array $snapshot, array $heatmapPngAbsolutePaths, string $absolutePdfPath): void
    {
        $drivingViews = $this->viewportImagesToRows($heatmapPngAbsolutePaths['driving'] ?? null);
        $parkingViews = $this->viewportImagesToRows($heatmapPngAbsolutePaths['parking'] ?? null);

        /** @var array<string, mixed> $analytics */
        $analytics = $snapshot['analytics_snapshot'] ?? [];

        $html = View::make('reports.pdf-html', [
            'advertiserName' => $snapshot['campaign']['advertiser_name'] ?? '—',
            'campaignName' => $snapshot['campaign']['name'] ?? '—',
            'dateFrom' => $snapshot['date_from'] ?? '',
            'dateTo' => $snapshot['date_to'] ?? '',
            'vehicleCount' => count($snapshot['vehicle_ids'] ?? []),
            'analytics' => $analytics,
            'includeDriving' => ! empty($snapshot['settings']['include_driving_heatmap']),
            'includeParking' => ! empty($snapshot['settings']['include_parking_heatmap']),
            'drivingViewports' => $drivingViews,
            'parkingViewports' => $parkingViews,
        ])->render();

        $timeout = (int) config('reports.browsershot_timeout', 180);

        $shot = Browsershot::html($html)
            ->format('A4')
            ->margins(12, 12, 12, 12)
            ->timeout(max(30, $timeout))
            ->delay(500);

        BrowsershotConfigurator::apply($shot);

        $pdf = $shot->pdf();

        $dir = dirname($absolutePdfPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($absolutePdfPath, $pdf);
    }

    /**
     * @param  array<string, string>|null  $idToPath
     * @return list<array{label: string, base64: string|null}>
     */
    private function viewportImagesToRows(?array $idToPath): array
    {
        if ($idToPath === null || $idToPath === []) {
            return [];
        }

        $ordered = [];
        foreach (ReportHeatmapViewports::all() as $vp) {
            $id = $vp['id'];
            if (! isset($idToPath[$id])) {
                continue;
            }
            $ordered[] = [
                'label' => $vp['label'],
                'base64' => $this->fileToBase64($idToPath[$id]),
            ];
        }

        return $ordered;
    }

    private function fileToBase64(?string $path): ?string
    {
        if ($path === null || $path === '' || ! is_file($path)) {
            return null;
        }

        return base64_encode((string) file_get_contents($path));
    }
}
