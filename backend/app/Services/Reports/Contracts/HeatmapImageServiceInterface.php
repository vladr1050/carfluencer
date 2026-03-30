<?php

namespace App\Services\Reports\Contracts;

interface HeatmapImageServiceInterface
{
    /**
     * Render a heatmap PNG for the given filter contract (same as heatmap API).
     *
     * @param  list<int>  $vehicleIds
     * @param  string  $viewportId  {@see ReportHeatmapViewports} id (e.g. baltics, latvia, riga)
     * @param  list<array<string, mixed>>|null  $parkingTopLocations  When non-null and mode is parking, render
     *                                                                circle export from analytics top_locations instead of Leaflet.heat.
     */
    public function renderPng(
        int $campaignId,
        string $dateFrom,
        string $dateTo,
        array $vehicleIds,
        string $mode,
        string $absolutePath,
        string $viewportId = 'baltics',
        ?array $parkingTopLocations = null,
    ): void;
}
