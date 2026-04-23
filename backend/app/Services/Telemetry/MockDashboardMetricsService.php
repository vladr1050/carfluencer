<?php

namespace App\Services\Telemetry;

use App\Models\Campaign;
use App\Models\User;

/**
 * Deterministic mock metrics until telemetry / analytics API is connected.
 */
class MockDashboardMetricsService implements DashboardMetricsServiceInterface
{
    public function advertiserSummary(User $user): array
    {
        $campaigns = Campaign::query()->where('advertiser_id', $user->id)->get();
        $active = $campaigns->where('status', 'active')->count();
        $seed = $user->id + ($campaigns->count() * 7);

        return [
            'active_campaigns_count' => $active,
            'impressions' => 500_000 + ($seed * 1_337) % 5_000_000,
            'driving_distance_km' => 2_000 + ($seed * 11) % 20_000,
            'driving_time_hours' => 120 + ($seed * 3) % 800,
            'parking_time_hours' => 200 + ($seed * 5) % 1_200,
            'impression_engine' => [
                'total_gross_impressions' => null,
                'driving_impressions' => null,
                'parking_impressions' => null,
                'campaigns_with_snapshot' => 0,
                'campaigns_in_scope' => max(0, $campaigns->count()),
                'coverage' => 'none',
            ],
            'note' => 'Mock metrics for development. Set TELEMETRY_METRICS_URL for live integration.',
            'source' => 'mock',
        ];
    }
}
