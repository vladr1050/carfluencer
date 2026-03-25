<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignProof;
use App\Models\CampaignVehicle;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvertiserCampaignProofApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_advertiser_cannot_upload_proof_via_api(): void
    {
        Storage::fake('public');

        $advertiser = User::factory()->advertiser()->create();
        $mediaOwner = User::factory()->mediaOwner()->create();
        $vehicle = Vehicle::query()->create([
            'media_owner_id' => $mediaOwner->id,
            'brand' => 'Test',
            'model' => 'Car',
            'year' => 2024,
            'color_key' => 'black',
            'quantity' => 1,
            'imei' => '111111111111111',
            'status' => 'active',
        ]);
        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'Adv campaign',
            'status' => 'active',
            'created_by_admin' => false,
            'platform_commission_percent' => '10',
            'agency_commission_percent' => '0',
        ]);
        CampaignVehicle::query()->create([
            'campaign_id' => $campaign->id,
            'vehicle_id' => $vehicle->id,
            'placement_size_class' => 'S',
            'status' => 'active',
        ]);

        Sanctum::actingAs($advertiser);

        $this->postJson("/api/advertiser/campaigns/{$campaign->id}/proofs", [
            'vehicle_id' => $vehicle->id,
        ])->assertStatus(405);
    }

    public function test_advertiser_can_list_campaign_proofs(): void
    {
        Storage::fake('public');

        $advertiser = User::factory()->advertiser()->create();
        $mediaOwner = User::factory()->mediaOwner()->create();
        $vehicle = Vehicle::query()->create([
            'media_owner_id' => $mediaOwner->id,
            'brand' => 'Test',
            'model' => 'Car',
            'year' => 2024,
            'color_key' => 'black',
            'quantity' => 1,
            'imei' => '222222222222222',
            'status' => 'active',
        ]);
        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'With proofs',
            'status' => 'active',
            'created_by_admin' => false,
            'platform_commission_percent' => '10',
            'agency_commission_percent' => '0',
        ]);
        CampaignVehicle::query()->create([
            'campaign_id' => $campaign->id,
            'vehicle_id' => $vehicle->id,
            'placement_size_class' => 'S',
            'status' => 'active',
        ]);

        $path = 'campaign-proofs/list-test.jpg';
        Storage::disk('public')->put($path, 'fake');
        CampaignProof::query()->create([
            'campaign_id' => $campaign->id,
            'vehicle_id' => $vehicle->id,
            'uploaded_by_user_id' => $advertiser->id,
            'file_path' => $path,
            'status' => 'uploaded',
        ]);

        Sanctum::actingAs($advertiser);

        $this->getJson("/api/advertiser/campaigns/{$campaign->id}/proofs")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'uploaded')
            ->assertJsonPath('data.0.vehicle.brand', 'Test')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'vehicle_id', 'status', 'url', 'created_at', 'vehicle'],
                ],
            ]);
    }
}
