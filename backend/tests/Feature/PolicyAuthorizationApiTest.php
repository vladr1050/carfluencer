<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PolicyAuthorizationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_advertiser_cannot_view_another_advertisers_campaign(): void
    {
        $owner = User::factory()->advertiser()->create();
        $other = User::factory()->advertiser()->create();
        $campaign = Campaign::query()->create([
            'advertiser_id' => $owner->id,
            'name' => 'Private',
            'status' => 'draft',
            'created_by_admin' => false,
            'platform_commission_percent' => '10',
            'agency_commission_percent' => '0',
        ]);

        Sanctum::actingAs($other);

        $this->getJson("/api/advertiser/campaigns/{$campaign->id}")->assertForbidden();
    }

    public function test_media_owner_cannot_view_campaign_without_participation(): void
    {
        $mediaOwner = User::factory()->mediaOwner()->create();
        $advertiser = User::factory()->advertiser()->create();
        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'Solo',
            'status' => 'active',
            'created_by_admin' => false,
            'platform_commission_percent' => '10',
            'agency_commission_percent' => '0',
        ]);

        Sanctum::actingAs($mediaOwner);

        $this->getJson("/api/media-owner/campaigns/{$campaign->id}")->assertForbidden();
    }
}
