<?php

namespace App\Services\Reports\Contracts;

interface CampaignReportPdfServiceInterface
{
    /**
     * @param  array<string, mixed>  $snapshot  Full snapshot payload for the HTML template.
     * @param  array<string, array<string, string>>  $heatmapPngAbsolutePaths  motion (driving|parking) → viewport id → absolute fs path
     */
    public function renderPdf(array $snapshot, array $heatmapPngAbsolutePaths, string $absolutePdfPath): void;
}
