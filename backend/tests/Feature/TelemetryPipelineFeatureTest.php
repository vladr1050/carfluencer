<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignVehicle;
use App\Models\DailyImpression;
use App\Models\DailyZoneImpression;
use App\Models\DeviceLocation;
use App\Models\GeoZone;
use App\Models\StopSession;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Telemetry\DailyImpressionAggregateService;
use App\Services\Telemetry\StopSessionBuilderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetryPipelineFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_pipeline_from_device_locations(): void
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
            'imei' => '888888888888888',
            'status' => 'active',
        ]);

        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'Telemetry campaign',
            'status' => 'active',
            'created_by_admin' => false,
            'platform_commission_percent' => '10',
            'agency_commission_percent' => '0',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        CampaignVehicle::query()->create([
            'campaign_id' => $campaign->id,
            'vehicle_id' => $vehicle->id,
            'placement_size_class' => 'M',
            'status' => 'active',
        ]);

        GeoZone::query()->create([
            'code' => 'LDN-CORE',
            'name' => 'London core',
            'min_lat' => 51.50,
            'max_lat' => 51.52,
            'min_lng' => -0.10,
            'max_lng' => -0.08,
            'active' => true,
        ]);

        $day = Carbon::parse('2026-03-20 10:00:00', 'UTC');
        for ($i = 0; $i < 5; $i++) {
            DeviceLocation::query()->create([
                'device_id' => $vehicle->imei,
                'event_at' => $day->copy()->addMinutes($i),
                'latitude' => 51.505,
                'longitude' => -0.09,
                'speed' => 0,
                'battery' => null,
                'gsm_signal' => null,
                'odometer' => null,
                'ignition' => null,
                'extra_json' => null,
            ]);
        }

        app(StopSessionBuilderService::class)->buildForDate($day->copy()->startOfDay());
        $this->assertGreaterThan(0, StopSession::query()->count());

        app(DailyImpressionAggregateService::class)->aggregateForDate($day->copy()->startOfDay());

        $this->assertDatabaseHas('daily_impressions', [
            'campaign_id' => $campaign->id,
            'vehicle_id' => $vehicle->id,
        ]);

        $this->assertGreaterThan(0, DailyImpression::query()->where('campaign_id', $campaign->id)->value('impressions'));
        $this->assertGreaterThan(0, DailyZoneImpression::query()->count());
    }
}
