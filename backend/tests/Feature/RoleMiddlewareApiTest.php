<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleMiddlewareApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_advertiser_cannot_access_media_owner_routes(): void
    {
        $advertiser = User::factory()->advertiser()->create();

        Sanctum::actingAs($advertiser);

        $response = $this->getJson('/api/media-owner/dashboard');

        $response->assertForbidden();
    }

    public function test_media_owner_can_access_own_dashboard(): void
    {
        $mediaOwner = User::factory()->mediaOwner()->create();

        Sanctum::actingAs($mediaOwner);

        $response = $this->getJson('/api/media-owner/dashboard');

        $response->assertOk()
            ->assertJsonStructure(['vehicles_count', 'active_campaign_participations']);
    }
}
