<?php

namespace App\Services\Reports;

use App\Services\Reports\Contracts\CampaignReportPdfServiceInterface;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;

final class BrowsershotCampaignReportPdfService implements CampaignReportPdfServiceInterface
{
    public function renderPdf(array $snapshot, array $heatmapPngAbsolutePaths, string $absolutePdfPath): void
    {
        $drivingB64 = $this->fileToBase64($heatmapPngAbsolutePaths['driving'] ?? null);
        $parkingB64 = $this->fileToBase64($heatmapPngAbsolutePaths['parking'] ?? null);

        $kpis = $snapshot['kpis'] ?? [];

        $html = View::make('reports.pdf-html', [
            'advertiserName' => $snapshot['campaign']['advertiser_name'] ?? '—',
            'campaignName' => $snapshot['campaign']['name'] ?? '—',
            'dateFrom' => $snapshot['date_from'] ?? '',
            'dateTo' => $snapshot['date_to'] ?? '',
            'vehicleCount' => count($snapshot['vehicle_ids'] ?? []),
            'dataSource' => $kpis['data_source'] ?? '—',
            'isEstimated' => ! empty($kpis['is_estimated']),
            'kpis' => $kpis,
            'includeDriving' => ! empty($snapshot['settings']['include_driving_heatmap']),
            'includeParking' => ! empty($snapshot['settings']['include_parking_heatmap']),
            'drivingImageBase64' => $drivingB64,
            'parkingImageBase64' => $parkingB64,
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

    private function fileToBase64(?string $path): ?string
    {
        if ($path === null || $path === '' || ! is_file($path)) {
            return null;
        }

        return base64_encode((string) file_get_contents($path));
    }
}
