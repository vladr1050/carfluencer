<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AdminTelemetryHeatmapController;
use App\Models\Campaign;
use App\Models\CampaignVehicle;
use App\Models\DeviceLocation;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Telemetry\AdminHeatmapDataService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminTelemetryHeatmapTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_heatmap_data(): void
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
            'imei' => '111111111111111',
            'status' => 'active',
        ]);

        $this->actingAs($advertiser);
        $this->getJson('/internal/admin/telemetry/heatmap-data?scope=vehicle&vehicle_id='.$vehicle->id.'&motion=both')
            ->assertForbidden();
    }

    public function test_admin_receives_heatmap_json_for_single_vehicle(): void
    {
        $admin = User::factory()->admin()->create();
        $mo = User::factory()->mediaOwner()->create();
        $vehicle = Vehicle::query()->create([
            'media_owner_id' => $mo->id,
            'brand' => 'T',
            'model' => 'M',
            'year' => 2024,
            'color_key' => 'black',
            'quantity' => 1,
            'imei' => '222222222222222',
            'status' => 'active',
        ]);

        DeviceLocation::query()->create([
            'device_id' => $vehicle->imei,
            'event_at' => '2026-03-10 12:00:00',
            'latitude' => 54.5,
            'longitude' => 25.5,
            'speed' => 40,
            'battery' => null,
            'gsm_signal' => null,
            'odometer' => null,
            'ignition' => true,
            'extra_json' => null,
        ]);

        $this->actingAs($admin);
        $this->getJson('/internal/admin/telemetry/heatmap-data?scope=vehicle&vehicle_id='.$vehicle->id.'&date_from=2026-03-01&date_to=2026-03-31&motion=both')
            ->assertOk()
            ->assertJsonPath('vehicles.0.imei', $vehicle->imei)
            ->assertJsonStructure(['heatmap' => ['points', 'metrics'], 'filter']);
    }

    public function test_moving_filter_excludes_low_speed_points(): void
    {
        $admin = User::factory()->admin()->create();
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

        DeviceLocation::query()->create([
            'device_id' => $vehicle->imei,
            'event_at' => '2026-03-10 10:00:00',
            'latitude' => 54.0,
            'longitude' => 25.0,
            'speed' => 0,
            'battery' => null,
            'gsm_signal' => null,
            'odometer' => null,
            'ignition' => true,
            'extra_json' => null,
        ]);
        DeviceLocation::query()->create([
            'device_id' => $vehicle->imei,
            'event_at' => '2026-03-10 11:00:00',
            'latitude' => 54.1,
            'longitude' => 25.1,
            'speed' => 50,
            'battery' => null,
            'gsm_signal' => null,
            'odometer' => null,
            'ignition' => true,
            'extra_json' => null,
        ]);

        $this->actingAs($admin);
        $response = $this->getJson('/internal/admin/telemetry/heatmap-data?scope=vehicle&vehicle_id='.$vehicle->id.'&date_from=2026-03-01&date_to=2026-03-31&motion=moving');
        $response->assertOk();
        $this->assertSame(1, (int) data_get($response->json(), 'heatmap.metrics.location_samples'));
    }

    public function test_campaign_scope_returns_linked_vehicles(): void
    {
        $admin = User::factory()->admin()->create();
        $advertiser = User::factory()->advertiser()->create();
        $mo = User::factory()->mediaOwner()->create();

        $v = Vehicle::query()->create([
            'media_owner_id' => $mo->id,
            'brand' => 'A',
            'model' => 'X',
            'year' => 2024,
            'color_key' => 'black',
            'quantity' => 1,
            'imei' => '444444444444444',
            'status' => 'active',
        ]);

        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'C1',
            'status' => 'active',
            'created_by_admin' => false,
            'platform_commission_percent' => '10',
            'agency_commission_percent' => '0',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        CampaignVehicle::query()->create([
            'campaign_id' => $campaign->id,
            'vehicle_id' => $v->id,
            'placement_size_class' => 'M',
            'status' => 'active',
        ]);

        DeviceLocation::query()->create([
            'device_id' => $v->imei,
            'event_at' => '2026-03-10 12:00:00',
            'latitude' => 55.0,
            'longitude' => 26.0,
            'speed' => 10,
            'battery' => null,
            'gsm_signal' => null,
            'odometer' => null,
            'ignition' => true,
            'extra_json' => null,
        ]);

        $this->actingAs($admin);
        $this->getJson('/internal/admin/telemetry/heatmap-data?scope=campaign&campaign_id='.$campaign->id.'&motion=both')
            ->assertOk()
            ->assertJsonPath('vehicles.0.id', $v->id);
    }

    public function test_internal_request_with_carbon_dates_applies_date_filter(): void
    {
        $admin = User::factory()->admin()->create();
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

        DeviceLocation::query()->create([
            'device_id' => $vehicle->imei,
            'event_at' => '2026-03-10 12:00:00',
            'latitude' => 54.5,
            'longitude' => 25.5,
            'speed' => 40,
            'battery' => null,
            'gsm_signal' => null,
            'odometer' => null,
            'ignition' => true,
            'extra_json' => null,
        ]);

        $req = Request::create(route('internal.admin.telemetry.heatmap-data'), 'GET', [
            'scope' => 'vehicle',
            'vehicle_id' => $vehicle->id,
            'date_from' => Carbon::parse('2026-03-01'),
            'date_to' => Carbon::parse('2026-03-31'),
            'motion' => 'both',
        ]);
        $req->setUserResolver(fn () => $admin);

        $response = app(AdminTelemetryHeatmapController::class)->data($req, app(AdminHeatmapDataService::class));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertGreaterThan(0, count($response->getData(true)['heatmap']['points'] ?? []));
    }
}
