<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvertiserMapBasemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_fetch_advertiser_map_basemap(): void
    {
        $this->getJson('/api/advertiser/map-basemap')->assertUnauthorized();
    }

    public function test_advertiser_receives_carto_positron_when_maptiler_key_missing(): void
    {
        config(['services.maptiler.api_key' => null]);

        $user = User::factory()->advertiser()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/advertiser/map-basemap');

        $response->assertOk()
            ->assertJsonPath('provider', 'carto')
            ->assertJsonPath('subdomains', 'abcd');

        $this->assertStringContainsString('basemaps.cartocdn.com', (string) $response->json('url'));
    }

    public function test_advertiser_receives_maptiler_when_key_set(): void
    {
        config(['services.maptiler.api_key' => 'test-key-123']);

        $user = User::factory()->advertiser()->create();
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/advertiser/map-basemap');

        $res->assertOk()
            ->assertJsonPath('provider', 'maptiler')
            ->assertJsonPath('subdomains', null);

        $url = (string) $res->json('url');
        $this->assertStringContainsString('api.maptiler.com/maps/positron/', $url);
        $this->assertStringContainsString('test-key-123', $url);
    }
}
