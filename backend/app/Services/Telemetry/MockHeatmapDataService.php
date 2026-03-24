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

        $buckets = [
            ['lat' => 51.505, 'lng' => -0.09, 'w_moving' => 80, 'w_stopped' => 10, 'w_total' => 90, 'intensity_moving' => 0.8, 'intensity_stopped' => 0.5, 'rank_moving_pct' => 0.0, 'rank_stopped_pct' => 33.0],
            ['lat' => 51.51, 'lng' => -0.1, 'w_moving' => 85, 'w_stopped' => 12, 'w_total' => 97, 'intensity_moving' => 0.85, 'intensity_stopped' => 0.55, 'rank_moving_pct' => 33.0, 'rank_stopped_pct' => 66.0],
            ['lat' => 51.515, 'lng' => -0.095, 'w_moving' => 90, 'w_stopped' => 15, 'w_total' => 105, 'intensity_moving' => 0.9, 'intensity_stopped' => 0.6, 'rank_moving_pct' => 66.0, 'rank_stopped_pct' => 0.0],
        ];

        return [
            'points' => $points,
            'buckets' => $buckets,
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
                'normalization' => 'p95',
                'cap_moving' => 100,
                'cap_stopped' => 20,
                'cap_total' => 110,
                'data_source' => 'mock',
            ],
        ];
    }
}
