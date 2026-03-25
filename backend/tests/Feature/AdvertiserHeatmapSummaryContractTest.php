<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignVehicle;
use App\Models\DailyImpression;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvertiserHeatmapSummaryContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_response_exposes_map_debug_and_summary_metrics_not_legacy_heatmap_key(): void
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
            'imei' => '555555555555555',
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
            'impressions' => 200,
            'driving_distance_km' => 10,
            'parking_minutes' => 60,
        ]);

        Sanctum::actingAs($advertiser);

        $url = '/api/advertiser/heatmap?campaign_id='.$campaign->id
            .'&date_from=2026-03-01&date_to=2026-03-31&mode=driving'
            .'&south=54&north=56&west=24&east=27&zoom=11';

        $res = $this->getJson($url)->assertOk();
        $json = $res->json();
        $this->assertArrayHasKey('map', $json);
        $this->assertArrayHasKey('debug', $json);
        $this->assertArrayHasKey('summary_metrics', $json);
        $this->assertArrayNotHasKey('heatmap', $json);

        $sm = $json['summary_metrics'];
        $this->assertArrayHasKey('impressions', $sm);
        $this->assertArrayHasKey('driving_distance_km', $sm);
        $this->assertArrayHasKey('driving_time_hours', $sm);
        $this->assertArrayHasKey('parking_time_hours', $sm);
        $this->assertArrayHasKey('data_source', $sm);
        $this->assertArrayHasKey('is_estimated', $sm);
        $this->assertSame(200, $sm['impressions']);
        $this->assertSame('daily_impressions', $sm['data_source']);
        $this->assertFalse($sm['is_estimated']);

        $this->assertArrayNotHasKey('location_samples_viewport', $sm);
        $this->assertArrayNotHasKey('cap_moving', $sm);
    }

    public function test_summary_metrics_identical_for_different_viewports_when_daily_data_present(): void
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
            'placement_size_class' => 'M',
            'status' => 'active',
        ]);

        DailyImpression::query()->create([
            'stat_date' => '2026-03-15',
            'campaign_id' => $campaign->id,
            'vehicle_id' => $vehicle->id,
            'impressions' => 99,
            'driving_distance_km' => 5,
            'parking_minutes' => 0,
        ]);

        Sanctum::actingAs($advertiser);

        $q = 'campaign_id='.$campaign->id.'&date_from=2026-03-01&date_to=2026-03-31&mode=driving';
        $a = $this->getJson('/api/advertiser/heatmap?'.$q.'&south=54&north=55&west=24&east=26&zoom=10')->assertOk();
        $b = $this->getJson('/api/advertiser/heatmap?'.$q.'&south=55&north=57&west=25&east=28&zoom=14')->assertOk();

        $this->assertSame($a->json('summary_metrics'), $b->json('summary_metrics'));
    }

    public function test_changing_date_range_updates_summary_metrics(): void
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
            'imei' => '777777777777777',
            'status' => 'active',
        ]);

        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'C3',
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
            'stat_date' => '2026-03-01',
            'campaign_id' => $campaign->id,
            'vehicle_id' => $vehicle->id,
            'impressions' => 10,
            'driving_distance_km' => 1,
            'parking_minutes' => 0,
        ]);
        DailyImpression::query()->create([
            'stat_date' => '2026-04-01',
            'campaign_id' => $campaign->id,
            'vehicle_id' => $vehicle->id,
            'impressions' => 5000,
            'driving_distance_km' => 100,
            'parking_minutes' => 0,
        ]);

        Sanctum::actingAs($advertiser);

        $base = '/api/advertiser/heatmap?campaign_id='.$campaign->id.'&mode=driving&south=54&north=56&west=24&east=27&zoom=11';
        $march = $this->getJson($base.'&date_from=2026-03-01&date_to=2026-03-31')->assertOk();
        $april = $this->getJson($base.'&date_from=2026-04-01&date_to=2026-04-30')->assertOk();

        $this->assertSame(10, $march->json('summary_metrics.impressions'));
        $this->assertSame(5000, $april->json('summary_metrics.impressions'));
    }
}
