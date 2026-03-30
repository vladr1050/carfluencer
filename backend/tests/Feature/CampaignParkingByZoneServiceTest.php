<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignVehicle;
use App\Models\GeoZone;
use App\Models\StopSession;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Analytics\CampaignParkingByZoneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignParkingByZoneServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_attributes_clipped_minutes_to_geo_zone_by_session_center(): void
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
            'imei' => '863540060119999',
            'status' => 'active',
        ]);

        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'Z',
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

        $zone = GeoZone::query()->create([
            'code' => 'RIGA-TEST',
            'name' => 'Riga test box',
            'min_lat' => 56.94,
            'max_lat' => 56.96,
            'min_lng' => 24.09,
            'max_lng' => 24.12,
            'active' => true,
        ]);

        StopSession::query()->create([
            'device_id' => $vehicle->imei,
            'started_at' => '2026-03-15 10:00:00',
            'ended_at' => '2026-03-15 11:30:00',
            'center_latitude' => 56.951,
            'center_longitude' => 24.105,
            'point_count' => 3,
            'kind' => 'parking',
        ]);

        $svc = app(CampaignParkingByZoneService::class);
        $out = $svc->build($campaign->id, '2026-03-15', '2026-03-15', []);

        $this->assertSame(90, $out['totals']['parking_minutes_in_window']);
        $this->assertSame(1, $out['totals']['parking_sessions_in_window']);
        $this->assertCount(1, $out['by_zone']);
        $this->assertSame($zone->id, $out['by_zone'][0]['zone_id']);
        $this->assertSame(90, $out['by_zone'][0]['parking_minutes']);
        $this->assertSame(0, $out['unattributed']['parking_minutes']);
        $this->assertSame(1, $out['by_vehicle'][0]['vehicle_id']);
        $this->assertSame(90, $out['by_vehicle'][0]['parking_minutes_total']);
        $this->assertSame(0, $out['by_vehicle'][0]['unattributed_parking_minutes']);
    }

    public function test_outside_zone_goes_unattributed(): void
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
            'imei' => '863540060129999',
            'status' => 'active',
        ]);

        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'Z2',
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

        GeoZone::query()->create([
            'code' => 'FAR',
            'name' => 'Far away',
            'min_lat' => 57.0,
            'max_lat' => 57.1,
            'min_lng' => 24.5,
            'max_lng' => 24.6,
            'active' => true,
        ]);

        StopSession::query()->create([
            'device_id' => $vehicle->imei,
            'started_at' => '2026-03-15 12:00:00',
            'ended_at' => '2026-03-15 12:20:00',
            'center_latitude' => 56.951,
            'center_longitude' => 24.105,
            'point_count' => 1,
            'kind' => 'parking',
        ]);

        $out = app(CampaignParkingByZoneService::class)->build($campaign->id, '2026-03-15', '2026-03-15', []);

        $this->assertSame(20, $out['totals']['parking_minutes_in_window']);
        $this->assertSame([], $out['by_zone']);
        $this->assertSame(20, $out['unattributed']['parking_minutes']);
        $this->assertSame(1, $out['unattributed']['sessions_count']);
    }
}
