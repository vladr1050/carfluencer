<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignVehicle;
use App\Models\DeviceLocation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvertiserHeatmapMotionFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_advertiser_heatmap_parking_mode_excludes_high_speed_points(): void
    {
        $advertiser = User::factory()->advertiser()->create();
        $mo = User::factory()->mediaOwner()->create();

        $vehicle = Vehicle::query()->create([
            'media_owner_id' => $mo->id,
            'brand' => 'T',
            'model' => 'M',
            'year' => 2024,
            'color_key' => 'black',
            'quantity' => 1,
            'imei' => '333333333333333',
            'status' => 'active',
        ]);

        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'H',
            'status' => 'active',
            'created_by_admin' => false,
            'platform_commission_percent' => '10',
            'agency_commission_percent' => '0',
        ]);

        CampaignVehicle::query()->create([
            'campaign_id' => $campaign->id,
            'vehicle_id' => $vehicle->id,
            'placement_size_class' => 'M',
            'status' => 'active',
        ]);

        // Bucket A — "stopped": low speed (rounded 54.5, 25.5)
        DeviceLocation::query()->create([
            'device_id' => $vehicle->imei,
            'event_at' => '2026-03-10 12:00:00',
            'latitude' => 54.5001,
            'longitude' => 25.5001,
            'speed' => 0,
            'battery' => null,
            'gsm_signal' => null,
            'odometer' => null,
            'ignition' => true,
            'extra_json' => null,
        ]);

        // Bucket B — "moving": high speed (54.6, 25.5)
        DeviceLocation::query()->create([
            'device_id' => $vehicle->imei,
            'event_at' => '2026-03-10 13:00:00',
            'latitude' => 54.6001,
            'longitude' => 25.5001,
            'speed' => 45,
            'battery' => null,
            'gsm_signal' => null,
            'odometer' => null,
            'ignition' => true,
            'extra_json' => null,
        ]);

        Sanctum::actingAs($advertiser);

        $base = '/api/advertiser/heatmap?campaign_id='.$campaign->id.'&date_from=2026-03-01&date_to=2026-03-31';

        $parking = $this->getJson($base.'&mode=parking')->assertOk();
        $parkingLats = collect($parking->json('heatmap.points'))->pluck('lat')->all();
        $this->assertContains(54.5, $parkingLats);
        $this->assertNotContains(54.6, $parkingLats);
        $this->assertSame('stopped', $parking->json('heatmap.metrics.heatmap_motion'));

        $driving = $this->getJson($base.'&mode=driving')->assertOk();
        $drivingLats = collect($driving->json('heatmap.points'))->pluck('lat')->all();
        $this->assertContains(54.6, $drivingLats);
        $this->assertNotContains(54.5, $drivingLats);
        $this->assertSame('moving', $driving->json('heatmap.metrics.heatmap_motion'));

        $this->getJson($base.'&mode=both')->assertUnprocessable();
    }
}
