<?php

namespace App\Services\Reports\Contracts;

interface HeatmapImageServiceInterface
{
    /**
     * Render a heatmap PNG for the given filter contract (same as heatmap API).
     *
     * @param  list<int>  $vehicleIds
     * @param  string  $viewportId  {@see ReportHeatmapViewports} id (e.g. full, riga_jurmala)
     */
    public function renderPng(
        int $campaignId,
        string $dateFrom,
        string $dateTo,
        array $vehicleIds,
        string $mode,
        string $absolutePath,
        string $viewportId = 'full',
    ): void;
}
