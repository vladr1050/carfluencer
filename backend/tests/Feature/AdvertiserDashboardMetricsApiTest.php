<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\DailyImpression;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvertiserDashboardMetricsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_database_metrics_when_no_remote_url(): void
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
            ->assertJsonPath('source', 'database')
            ->assertJsonPath('active_campaigns_count', 0)
            ->assertJsonPath('impressions', 0);
    }

    public function test_dashboard_sums_daily_impressions_for_advertiser_campaigns(): void
    {
        $mediaOwner = User::factory()->mediaOwner()->create();
        $vehicle = Vehicle::query()->create([
            'media_owner_id' => $mediaOwner->id,
            'brand' => 'Test',
            'model' => 'Van',
            'imei' => 'IMEI'.Str::upper(Str::random(10)),
            'status' => 'active',
        ]);

        $advertiser = User::factory()->advertiser()->create();
        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'Summer wrap',
            'status' => 'active',
        ]);

        $campaign->vehicles()->attach($vehicle->id, [
            'placement_size_class' => 'M',
            'agreed_price' => 100,
            'status' => 'active',
        ]);

        DailyImpression::query()->create([
            'stat_date' => now()->toDateString(),
            'campaign_id' => $campaign->id,
            'vehicle_id' => $vehicle->id,
            'impressions' => 1000,
            'driving_distance_km' => 70,
            'parking_minutes' => 120,
        ]);

        Sanctum::actingAs($advertiser);

        $this->getJson('/api/advertiser/dashboard')
            ->assertOk()
            ->assertJsonPath('source', 'database')
            ->assertJsonPath('active_campaigns_count', 1)
            ->assertJsonPath('impressions', 1000)
            ->assertJsonPath('driving_distance_km', 70)
            ->assertJsonPath('driving_time_hours', 2)
            ->assertJsonPath('parking_time_hours', 2);
    }
}
