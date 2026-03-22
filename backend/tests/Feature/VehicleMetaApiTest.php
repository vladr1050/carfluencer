<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleMetaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_owner_receives_vehicle_field_meta(): void
    {
        Sanctum::actingAs(User::factory()->mediaOwner()->create());

        $response = $this->getJson('/api/meta/vehicle-fields');

        $response->assertOk()
            ->assertJsonStructure([
                'colors',
                'fleet_statuses',
                'catalog_statuses',
            ]);

        $this->assertNotEmpty($response->json('colors'));
        $this->assertNotEmpty($response->json('fleet_statuses'));
    }

    public function test_advertiser_receives_vehicle_field_meta(): void
    {
        Sanctum::actingAs(User::factory()->advertiser()->create());

        $this->getJson('/api/meta/vehicle-fields')->assertOk();
    }

    public function test_admin_cannot_access_vehicle_meta_endpoint(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/meta/vehicle-fields')->assertForbidden();
    }
}
