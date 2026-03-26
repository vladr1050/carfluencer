<?php

namespace App\Services\Reports;

use App\Services\Reports\Contracts\CampaignReportPdfServiceInterface;

/**
 * Minimal valid PDF placeholder for tests / CI without Chrome.
 */
final class FakeCampaignReportPdfService implements CampaignReportPdfServiceInterface
{
    public function renderPdf(array $snapshot, array $heatmapPngAbsolutePaths, string $absolutePdfPath): void
    {
        $dir = dirname($absolutePdfPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $minimal = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";
        file_put_contents($absolutePdfPath, $minimal);
    }
}
