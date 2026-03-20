<?php

namespace App\Services\Telemetry;

/**
 * Production-ready abstraction for telemetry / heatmap data.
 * Replace MockHeatmapDataService with a real implementation when the collector API is available.
 */
interface HeatmapDataServiceInterface
{
    /**
     * @param  array{vehicle_ids?: array<int>, date_from?: string, date_to?: string, mode?: string}  $filters
     * @return array{points: array<int, array{lat: float, lng: float, intensity: float}>, metrics: array<string, mixed>}
     */
    public function fetchHeatmapData(int $campaignId, array $filters = []): array;
}
