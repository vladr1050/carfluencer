<?php

namespace App\Services\Telemetry;

/**
 * Production-ready abstraction for telemetry / heatmap data.
 * Replace MockHeatmapDataService with a real implementation when the collector API is available.
 */
interface HeatmapDataServiceInterface
{
    /**
     * @param  array{
     *     vehicle_ids?: array<int>,
     *     date_from?: string,
     *     date_to?: string,
     *     mode?: string,
     *     normalization?: string,
     *     south?: float|string|null,
     *     west?: float|string|null,
     *     north?: float|string|null,
     *     east?: float|string|null,
     *     zoom?: int|string|null
     * }  $filters
     * @return array{
     *     map: array<string, mixed>,
     *     debug: array<string, mixed>,
     *     summary_metrics: array<string, mixed>
     * }
     */
    public function fetchHeatmapData(int $campaignId, array $filters = []): array;
}
