<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignProof;
use App\Models\CampaignVehicle;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MediaOwnerCampaignProofApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_owner_can_upload_proof_for_campaign_vehicle(): void
    {
        Storage::fake('public');

        $mediaOwner = User::factory()->mediaOwner()->create();
        $advertiser = User::factory()->advertiser()->create();
        $vehicle = Vehicle::query()->create([
            'media_owner_id' => $mediaOwner->id,
            'brand' => 'Test',
            'model' => 'Car',
            'year' => 2024,
            'color_key' => 'black',
            'quantity' => 1,
            'imei' => '123456789012345',
            'status' => 'active',
        ]);
        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'Test campaign',
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

        Sanctum::actingAs($mediaOwner);

        $file = UploadedFile::fake()->image('proof.jpg', 100, 100);

        $response = $this->post(
            "/api/media-owner/campaigns/{$campaign->id}/proofs",
            [
                'vehicle_id' => $vehicle->id,
                'file' => $file,
            ],
            ['Accept' => 'application/json']
        );

        $response->assertCreated()
            ->assertJsonPath('status', 'uploaded')
            ->assertJsonStructure(['id', 'file_path', 'url']);

        Storage::disk('public')->assertExists($response->json('file_path'));
    }

    public function test_rejects_vehicle_not_on_campaign(): void
    {
        Storage::fake('public');

        $mediaOwner = User::factory()->mediaOwner()->create();
        $advertiser = User::factory()->advertiser()->create();
        $vehicle = Vehicle::query()->create([
            'media_owner_id' => $mediaOwner->id,
            'brand' => 'Test',
            'model' => 'Car',
            'year' => 2024,
            'color_key' => 'black',
            'quantity' => 1,
            'imei' => '999999999999999',
            'status' => 'active',
        ]);
        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'Empty campaign',
            'status' => 'active',
            'created_by_admin' => false,
            'platform_commission_percent' => '10',
            'agency_commission_percent' => '0',
        ]);

        Sanctum::actingAs($mediaOwner);

        $file = UploadedFile::fake()->image('proof.jpg');

        $response = $this->post(
            "/api/media-owner/campaigns/{$campaign->id}/proofs",
            [
                'vehicle_id' => $vehicle->id,
                'file' => $file,
            ],
            ['Accept' => 'application/json']
        );

        $response->assertForbidden();
    }

    public function test_media_owner_lists_only_proofs_for_own_vehicles(): void
    {
        Storage::fake('public');

        $mo1 = User::factory()->mediaOwner()->create();
        $mo2 = User::factory()->mediaOwner()->create();
        $advertiser = User::factory()->advertiser()->create();

        $v1 = Vehicle::query()->create([
            'media_owner_id' => $mo1->id,
            'brand' => 'A',
            'model' => 'One',
            'year' => 2024,
            'color_key' => 'black',
            'quantity' => 1,
            'imei' => '333333333333333',
            'status' => 'active',
        ]);
        $v2 = Vehicle::query()->create([
            'media_owner_id' => $mo2->id,
            'brand' => 'B',
            'model' => 'Two',
            'year' => 2024,
            'color_key' => 'white',
            'quantity' => 1,
            'imei' => '444444444444444',
            'status' => 'active',
        ]);

        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'Shared',
            'status' => 'active',
            'created_by_admin' => false,
            'platform_commission_percent' => '10',
            'agency_commission_percent' => '0',
        ]);
        foreach ([$v1, $v2] as $v) {
            CampaignVehicle::query()->create([
                'campaign_id' => $campaign->id,
                'vehicle_id' => $v->id,
                'placement_size_class' => 'M',
                'status' => 'active',
            ]);
        }

        $p1 = 'campaign-proofs/mo1.jpg';
        $p2 = 'campaign-proofs/mo2.jpg';
        Storage::disk('public')->put($p1, 'a');
        Storage::disk('public')->put($p2, 'b');
        CampaignProof::query()->create([
            'campaign_id' => $campaign->id,
            'vehicle_id' => $v1->id,
            'uploaded_by_user_id' => $mo1->id,
            'file_path' => $p1,
            'status' => 'uploaded',
        ]);
        CampaignProof::query()->create([
            'campaign_id' => $campaign->id,
            'vehicle_id' => $v2->id,
            'uploaded_by_user_id' => $mo2->id,
            'file_path' => $p2,
            'status' => 'uploaded',
        ]);

        Sanctum::actingAs($mo1);

        $this->getJson("/api/media-owner/campaigns/{$campaign->id}/proofs")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.vehicle_id', $v1->id);
    }
}
