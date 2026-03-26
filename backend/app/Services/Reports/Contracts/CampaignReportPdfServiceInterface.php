<?php

namespace App\Services\Reports\Contracts;

interface CampaignReportPdfServiceInterface
{
    /**
     * @param  array<string, mixed>  $snapshot  Full snapshot payload for the HTML template.
     * @param  array<string, string>  $heatmapPngAbsolutePaths  keys: driving, parking (optional)
     */
    public function renderPdf(array $snapshot, array $heatmapPngAbsolutePaths, string $absolutePdfPath): void;
}
