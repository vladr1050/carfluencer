<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignVehicle;
use App\Models\GeoZone;
use App\Models\StopSession;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvertiserCampaignParkingByZoneApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_advertiser_can_fetch_parking_by_zone_json(): void
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
            'imei' => '863540060139999',
            'status' => 'active',
        ]);

        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'API Z',
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
            'code' => 'API-BOX',
            'name' => 'API box',
            'min_lat' => 56.94,
            'max_lat' => 56.96,
            'min_lng' => 24.09,
            'max_lng' => 24.12,
            'active' => true,
        ]);

        StopSession::query()->create([
            'device_id' => $vehicle->imei,
            'started_at' => '2026-03-10 08:00:00',
            'ended_at' => '2026-03-10 08:45:00',
            'center_latitude' => 56.951,
            'center_longitude' => 24.105,
            'point_count' => 2,
            'kind' => 'parking',
        ]);

        Sanctum::actingAs($advertiser);

        $url = '/api/advertiser/campaigns/'.$campaign->id.'/parking-by-zone?date_from=2026-03-10&date_to=2026-03-10';
        $res = $this->getJson($url);
        $res->assertOk();
        $res->assertJsonPath('campaign.id', $campaign->id);
        $res->assertJsonPath('parking_by_zone.totals.parking_minutes_in_window', 45);
        $res->assertJsonPath('parking_by_zone.totals.parking_sessions_in_window', 1);
        $this->assertNotEmpty($res->json('parking_by_zone.by_zone'));
    }
}
