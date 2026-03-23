<?php

namespace App\Services\Telemetry;

/**
 * Deterministic mock data for local development and until the real telemetry API is wired.
 */
class MockHeatmapDataService implements HeatmapDataServiceInterface
{
    public function fetchHeatmapData(int $campaignId, array $filters = []): array
    {
        $mode = $filters['mode'] ?? 'both';

        $points = [
            ['lat' => 51.505, 'lng' => -0.09, 'intensity' => 0.8],
            ['lat' => 51.51, 'lng' => -0.1, 'intensity' => 0.85],
            ['lat' => 51.515, 'lng' => -0.095, 'intensity' => 0.9],
        ];

        return [
            'points' => $points,
            'metrics' => [
                'impressions' => 2847350,
                'driving_distance_km' => 1247,
                'driving_time_hours' => 82,
                'parking_time_hours' => 156,
                'mode' => $mode,
                'heatmap_motion' => match ($mode) {
                    'parking' => 'stopped',
                    'driving' => 'moving',
                    default => 'both',
                },
                'campaign_id' => $campaignId,
                'intensity_gamma' => TelemetryHeatmapConfig::intensityGamma(),
                'data_source' => 'mock',
            ],
        ];
    }
}
