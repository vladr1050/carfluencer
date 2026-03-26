<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\CampaignVehicle;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Reports\CampaignReportVehicleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignReportVehicleResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_sorted_unique_vehicle_ids_on_campaign(): void
    {
        $advertiser = User::factory()->advertiser()->create();
        $mo = User::factory()->mediaOwner()->create();

        $v1 = Vehicle::query()->create([
            'media_owner_id' => $mo->id,
            'brand' => 'A',
            'model' => '1',
            'year' => 2024,
            'color_key' => 'black',
            'quantity' => 1,
            'imei' => '111111111111111',
            'status' => 'active',
        ]);
        $v2 = Vehicle::query()->create([
            'media_owner_id' => $mo->id,
            'brand' => 'B',
            'model' => '2',
            'year' => 2024,
            'color_key' => 'black',
            'quantity' => 1,
            'imei' => '222222222222222',
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
            'vehicle_id' => $v2->id,
            'placement_size_class' => 'M',
            'status' => 'active',
        ]);
        CampaignVehicle::query()->create([
            'campaign_id' => $campaign->id,
            'vehicle_id' => $v1->id,
            'placement_size_class' => 'M',
            'status' => 'active',
        ]);

        $resolver = new CampaignReportVehicleResolver;
        $this->assertSame([$v1->id, $v2->id], $resolver->resolveForCampaign($campaign->id));
    }
}
