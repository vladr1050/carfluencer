<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvertiserDashboardMetricsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_metrics_with_source(): void
    {
        $advertiser = User::factory()->advertiser()->create();

        Sanctum::actingAs($advertiser);

        $response = $this->getJson('/api/advertiser/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'active_campaigns_count',
                'impressions',
                'driving_distance_km',
                'driving_time_hours',
                'parking_time_hours',
                'source',
            ])
            ->assertJsonPath('source', 'mock');
    }
}
