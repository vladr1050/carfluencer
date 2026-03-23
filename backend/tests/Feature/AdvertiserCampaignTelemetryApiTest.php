<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignVehicle;
use App\Models\DailyImpression;
use App\Models\StopSession;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvertiserCampaignTelemetryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_show_includes_vehicle_telemetry_rollups(): void
    {
        $advertiser = User::factory()->advertiser()->create();
        $mediaOwner = User::factory()->mediaOwner()->create();

        $vehicle = Vehicle::query()->create([
            'media_owner_id' => $mediaOwner->id,
            'brand' => 'Test',
            'model' => 'Van',
            'year' => 2024,
            'color_key' => 'black',
            'quantity' => 1,
            'imei' => '777777777777777',
            'status' => 'active',
        ]);

        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'Wrap',
            'status' => 'active',
            'created_by_admin' => false,
            'platform_commission_percent' => '10',
            'agency_commission_percent' => '0',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        $cv = CampaignVehicle::query()->create([
            'campaign_id' => $campaign->id,
            'vehicle_id' => $vehicle->id,
            'placement_size_class' => 'M',
            'status' => 'active',
        ]);

        DailyImpression::query()->create([
            'stat_date' => '2026-06-01',
            'campaign_id' => $campaign->id,
            'vehicle_id' => $vehicle->id,
            'impressions' => 500,
            'driving_distance_km' => 70,
            'parking_minutes' => 60,
        ]);

        StopSession::query()->create([
            'device_id' => $vehicle->imei,
            'started_at' => '2026-06-01 10:00:00',
            'ended_at' => '2026-06-01 10:30:00',
            'center_latitude' => 56.9,
            'center_longitude' => 24.1,
            'point_count' => 5,
            'kind' => 'driving',
        ]);

        Sanctum::actingAs($advertiser);

        $this->getJson("/api/advertiser/campaigns/{$campaign->id}")
            ->assertOk()
            ->assertJsonPath('vehicle_telemetry.0.vehicle_id', $vehicle->id)
            ->assertJsonPath('vehicle_telemetry.0.campaign_vehicle_id', $cv->id)
            ->assertJsonPath('vehicle_telemetry.0.impressions', 500)
            ->assertJsonPath('vehicle_telemetry.0.driving_distance_km', 70)
            ->assertJsonPath('vehicle_telemetry.0.driving_time_hours', 0.5)
            ->assertJsonPath('vehicle_telemetry.0.parking_time_hours', 1);
    }
}
