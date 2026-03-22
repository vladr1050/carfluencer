<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignVehicle;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Telemetry\TelemetrySyncImeiResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetrySyncImeiResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_imeis_for_campaign_from_campaign_vehicles(): void
    {
        $advertiser = User::factory()->advertiser()->create();
        $mediaOwner = User::factory()->mediaOwner()->create();

        $v1 = Vehicle::query()->create([
            'media_owner_id' => $mediaOwner->id,
            'brand' => 'A',
            'model' => 'X',
            'year' => 2024,
            'color_key' => 'black',
            'quantity' => 1,
            'imei' => '111111111111111',
            'status' => 'active',
        ]);
        $v2 = Vehicle::query()->create([
            'media_owner_id' => $mediaOwner->id,
            'brand' => 'B',
            'model' => 'Y',
            'year' => 2024,
            'color_key' => 'white',
            'quantity' => 1,
            'imei' => '222222222222222',
            'status' => 'active',
        ]);
        Vehicle::query()->create([
            'media_owner_id' => $mediaOwner->id,
            'brand' => 'C',
            'model' => 'Z',
            'year' => 2024,
            'color_key' => 'red',
            'quantity' => 1,
            'imei' => '333333333333333',
            'status' => 'active',
        ]);

        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'Scope test',
            'status' => 'active',
            'created_by_admin' => false,
            'platform_commission_percent' => '10',
            'agency_commission_percent' => '0',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        CampaignVehicle::query()->create([
            'campaign_id' => $campaign->id,
            'vehicle_id' => $v1->id,
            'placement_size_class' => 'M',
            'status' => 'active',
        ]);
        CampaignVehicle::query()->create([
            'campaign_id' => $campaign->id,
            'vehicle_id' => $v2->id,
            'placement_size_class' => 'M',
            'status' => 'active',
        ]);

        $resolver = app(TelemetrySyncImeiResolver::class);
        $imeis = $resolver->resolve('campaign', $campaign->id, []);

        sort($imeis);

        $this->assertSame(['111111111111111', '222222222222222'], $imeis);
    }

    public function test_resolves_imeis_for_explicit_vehicle_ids(): void
    {
        $mediaOwner = User::factory()->mediaOwner()->create();
        $v = Vehicle::query()->create([
            'media_owner_id' => $mediaOwner->id,
            'brand' => 'A',
            'model' => 'X',
            'year' => 2024,
            'color_key' => 'black',
            'quantity' => 1,
            'imei' => '999999999999999',
            'status' => 'active',
        ]);

        $resolver = app(TelemetrySyncImeiResolver::class);
        $imeis = $resolver->resolve('vehicles', null, [$v->id]);

        $this->assertSame(['999999999999999'], $imeis);
    }
}
