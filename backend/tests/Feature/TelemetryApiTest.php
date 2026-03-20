<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignVehicle;
use App\Models\DeviceLocation;
use App\Models\DailyImpression;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TelemetryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_advertiser_can_fetch_raw_locations_for_campaign_vehicle(): void
    {
        $advertiser = User::factory()->advertiser()->create();
        $mediaOwner = User::factory()->mediaOwner()->create();

        $vehicle = Vehicle::query()->create([
            'media_owner_id' => $mediaOwner->id,
            'brand' => 'X',
            'model' => 'Y',
            'year' => 2024,
            'color' => 'Black',
            'quantity' => 1,
            'imei' => '777777777777777',
            'status' => 'active',
        ]);

        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'C1',
            'status' => 'active',
            'created_by_admin' => false,
            'platform_commission_percent' => '10',
            'agency_commission_percent' => '0',
        ]);

        CampaignVehicle::query()->create([
            'campaign_id' => $campaign->id,
            'vehicle_id' => $vehicle->id,
            'placement_size_class' => 'S',
            'status' => 'active',
        ]);

        DeviceLocation::query()->create([
            'device_id' => $vehicle->imei,
            'event_at' => '2026-03-10 12:00:00',
            'latitude' => 1.234567,
            'longitude' => 5.678901,
            'speed' => 10.5,
            'battery' => null,
            'gsm_signal' => null,
            'odometer' => null,
            'ignition' => true,
            'extra_json' => null,
        ]);

        Sanctum::actingAs($advertiser);

        $response = $this->getJson('/api/telemetry/locations/raw?'.http_build_query([
            'imei' => $vehicle->imei,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
        ]));

        $response->assertOk()
            ->assertJsonPath('data.0.device_id', $vehicle->imei)
            ->assertJsonPath('data.0.latitude', 1.234567);
    }

    public function test_advertiser_can_list_daily_impressions(): void
    {
        $advertiser = User::factory()->advertiser()->create();
        $mediaOwner = User::factory()->mediaOwner()->create();

        $vehicle = Vehicle::query()->create([
            'media_owner_id' => $mediaOwner->id,
            'brand' => 'A',
            'model' => 'B',
            'year' => 2024,
            'color' => 'Black',
            'quantity' => 1,
            'imei' => '666666666666666',
            'status' => 'active',
        ]);

        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'C2',
            'status' => 'active',
            'created_by_admin' => false,
            'platform_commission_percent' => '10',
            'agency_commission_percent' => '0',
        ]);

        CampaignVehicle::query()->create([
            'campaign_id' => $campaign->id,
            'vehicle_id' => $vehicle->id,
            'placement_size_class' => 'S',
            'status' => 'active',
        ]);

        DailyImpression::query()->create([
            'stat_date' => '2026-03-15',
            'campaign_id' => $campaign->id,
            'vehicle_id' => $vehicle->id,
            'impressions' => 1000,
            'driving_distance_km' => 12.5,
            'parking_minutes' => 30,
        ]);

        Sanctum::actingAs($advertiser);

        $this->getJson('/api/telemetry/impressions/daily')
            ->assertOk()
            ->assertJsonPath('data.0.impressions', 1000);
    }
}
