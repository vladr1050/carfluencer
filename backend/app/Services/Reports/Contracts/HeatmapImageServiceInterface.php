<?php

namespace App\Services\Reports\Contracts;

interface HeatmapImageServiceInterface
{
    /**
     * Render a heatmap PNG for the given filter contract (same as heatmap API).
     *
     * @param  list<int>  $vehicleIds
     */
    public function renderPng(
        int $campaignId,
        string $dateFrom,
        string $dateTo,
        array $vehicleIds,
        string $mode,
        string $absolutePath
    ): void;
}
