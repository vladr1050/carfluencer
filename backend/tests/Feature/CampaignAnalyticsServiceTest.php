<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignVehicle;
use App\Models\DailyImpression;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Analytics\CampaignAnalyticsService;
use App\Services\Telemetry\HeatmapBucketStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CampaignAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_empty_campaign_vehicles_returns_structure_and_zeros(): void
    {
        $advertiser = User::factory()->advertiser()->create();

        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'Empty',
            'status' => 'active',
            'created_by_admin' => false,
            'platform_commission_percent' => '10',
            'agency_commission_percent' => '0',
        ]);

        $svc = app(CampaignAnalyticsService::class);
        $snap = $svc->buildSnapshot($campaign->id, '2026-03-01', '2026-03-07', []);

        $this->assertSame($campaign->id, $snap['meta']['campaign_id']);
        $this->assertSame('2026-03-01', $snap['meta']['date_from']);
        $this->assertSame('2026-03-07', $snap['meta']['date_to']);
        $this->assertSame('v1', $snap['meta']['schema_version']);
        $this->assertSame([], $snap['meta']['vehicle_ids']);
        $this->assertSame('none', $snap['meta']['data_source']);
        $this->assertFalse($snap['meta']['is_estimated']);

        $this->assertSame(0, $snap['kpis']['impressions']);
        $this->assertSame(0, $snap['kpis']['carfluencers']);
        $this->assertSame(0.0, $snap['kpis']['km_driven']);
        $this->assertSame(0.0, $snap['kpis']['driving_hours']);
        $this->assertSame(0.0, $snap['kpis']['parking_hours']);
        $this->assertSame([], $snap['top_locations']);
        $this->assertArrayHasKey('parking_by_zone', $snap);
        $this->assertSame(0, $snap['parking_by_zone']['totals']['parking_minutes_in_window']);
        $this->assertTrue($snap['time_distribution']['day_vs_night']['is_stub']);
        $this->assertSame(0.75, $snap['time_distribution']['day_vs_night']['day']);
        $this->assertSame(0.25, $snap['time_distribution']['day_vs_night']['night']);

        $this->assertArrayHasKey('insights', $snap);
        $this->assertArrayHasKey('summary', $snap['insights']);
        $this->assertArrayHasKey('highlights', $snap['insights']);
        $this->assertArrayHasKey('exposure_pattern', $snap['insights']);
        $this->assertArrayHasKey('location_pattern', $snap['insights']);

        $this->assertIsArray($snap['coverage']);
        $this->assertArrayHasKey('unique_cells', $snap['coverage']);
        $this->assertArrayHasKey('reference_cells', $snap['coverage']);
        $this->assertArrayHasKey('coverage_ratio', $snap['coverage']);
        $this->assertArrayHasKey('coverage_pattern', $snap['coverage']);
        $this->assertArrayHasKey('method', $snap['coverage']);
        $this->assertArrayHasKey('denominator_scope', $snap['coverage']);
        $this->assertSame(0, $snap['coverage']['unique_cells']);
        $this->assertNull($snap['coverage']['coverage_pattern']);
        $this->assertArrayHasKey('coverage_narrative', $snap['coverage']);
        $this->assertNull($snap['coverage']['coverage_narrative']);
    }

    public function test_top_locations_from_heatmap_cells_daily_parking_and_kpis_from_daily_impressions(): void
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
            'imei' => '863540060109999',
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
            'impressions' => 100,
            'driving_distance_km' => 50,
            'parking_minutes' => 120,
        ]);

        $mapZoom = (int) config('reports.analytics.top_locations_map_zoom', 14);
        $tier = HeatmapBucketStrategy::tierFromMapZoom($mapZoom);

        DB::table('heatmap_cells_daily')->insert([
            'day' => '2026-03-10',
            'mode' => 'parking',
            'zoom_tier' => $tier,
            'lat_bucket' => 56.95,
            'lng_bucket' => 24.10,
            'device_id' => $vehicle->imei,
            'samples_count' => 500,
            'weight_value' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('heatmap_cells_daily')->insert([
            'day' => '2026-03-10',
            'mode' => 'parking',
            'zoom_tier' => $tier,
            'lat_bucket' => 57.0,
            'lng_bucket' => 24.2,
            'device_id' => $vehicle->imei,
            'samples_count' => 100,
            'weight_value' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $svc = app(CampaignAnalyticsService::class);
        $snap = $svc->buildSnapshot($campaign->id, '2026-03-01', '2026-03-31', []);

        $this->assertSame(100, $snap['kpis']['impressions']);
        $this->assertSame(50.0, $snap['kpis']['km_driven']);
        $this->assertSame(2.0, $snap['kpis']['parking_hours']);
        $this->assertGreaterThan(0, $snap['kpis']['carfluencers']);

        $this->assertCount(2, $snap['top_locations']);
        $this->assertSame(500, $snap['top_locations'][0]['samples']);
        $this->assertSame(500, $snap['top_locations'][0]['dwell_proxy']);
        $this->assertSame(56.95, $snap['top_locations'][0]['lat']);
        $this->assertSame(24.1, $snap['top_locations'][0]['lng']);
        $this->assertNull($snap['top_locations'][0]['label']);

        $totalHours = $snap['kpis']['driving_hours'] + $snap['kpis']['parking_hours'];
        if ($totalHours > 0) {
            $this->assertEqualsWithDelta(
                1.0,
                $snap['exposure_split']['driving_share'] + $snap['exposure_split']['parking_share'],
                0.0001
            );
        }
    }
}
