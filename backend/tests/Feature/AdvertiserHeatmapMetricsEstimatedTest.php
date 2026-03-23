<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignVehicle;
use App\Models\DailyImpression;
use App\Models\DeviceLocation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvertiserHeatmapMetricsEstimatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_heatmap_metrics_estimate_distance_when_daily_rollup_has_impressions_but_no_km(): void
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
            'imei' => '444444444444444',
            'status' => 'active',
        ]);

        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'C',
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

        DailyImpression::query()->create([
            'stat_date' => '2026-03-10',
            'campaign_id' => $campaign->id,
            'vehicle_id' => $vehicle->id,
            'impressions' => 500,
            'driving_distance_km' => null,
            'parking_minutes' => null,
        ]);

        DeviceLocation::query()->create([
            'device_id' => $vehicle->imei,
            'event_at' => '2026-03-10 12:00:00',
            'latitude' => 54.5001,
            'longitude' => 25.5001,
            'speed' => 40,
            'battery' => null,
            'gsm_signal' => null,
            'odometer' => null,
            'ignition' => true,
            'extra_json' => null,
        ]);
        DeviceLocation::query()->create([
            'device_id' => $vehicle->imei,
            'event_at' => '2026-03-10 12:30:00',
            'latitude' => 54.6001,
            'longitude' => 25.5001,
            'speed' => 40,
            'battery' => null,
            'gsm_signal' => null,
            'odometer' => null,
            'ignition' => true,
            'extra_json' => null,
        ]);

        Sanctum::actingAs($advertiser);

        $url = '/api/advertiser/heatmap?campaign_id='.$campaign->id.'&date_from=2026-03-01&date_to=2026-03-31&mode=both';
        $res = $this->getJson($url)->assertOk();

        $this->assertSame(500, $res->json('heatmap.metrics.impressions'));
        $this->assertSame('daily_impressions_estimated', $res->json('heatmap.metrics.data_source'));
        $km = (float) $res->json('heatmap.metrics.driving_distance_km');
        $this->assertGreaterThan(5.0, $km, 'Estimated driving distance should reflect GPS segment');
        $hours = (float) $res->json('heatmap.metrics.driving_time_hours');
        $this->assertGreaterThan(0.0, $hours, 'Driving time should derive from distance or sessions');
    }
}
