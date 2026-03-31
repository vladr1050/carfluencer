<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignImpressionStat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvertiserCampaignImpressionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_advertiser_receives_snapshot_when_it_exists(): void
    {
        $advertiser = User::factory()->advertiser()->create();
        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'On truck',
            'status' => 'active',
            'total_price' => 1200,
        ]);

        CampaignImpressionStat::query()->create([
            'campaign_id' => $campaign->id,
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-15',
            'vehicles_count' => 2,
            'driving_impressions' => 100,
            'parking_impressions' => 50,
            'total_gross_impressions' => 150,
            'campaign_price' => 1200,
            'cpm' => 8000,
            'calculation_version' => 'v1.0',
            'mobility_data_version' => 'riga_v1',
            'coefficients_version' => 'v1.0',
            'telemetry_sampling_seconds' => 10,
            'input_fingerprint' => str_repeat('a', 64),
            'matched_direct_count' => 10,
            'matched_fallback_count' => 1,
            'unmatched_count' => 2,
        ]);

        Sanctum::actingAs($advertiser);

        $this->getJson('/api/advertiser/campaigns/'.$campaign->id.'/impressions?date_from=2026-03-01&date_to=2026-03-15')
            ->assertOk()
            ->assertJsonPath('campaign_id', $campaign->id)
            ->assertJsonPath('total_gross_impressions', 150)
            ->assertJsonPath('driving_impressions', 100)
            ->assertJsonPath('parking_impressions', 50)
            ->assertJsonPath('mobility_data_version', 'riga_v1');
    }

    public function test_returns_404_when_no_snapshot_for_period(): void
    {
        $advertiser = User::factory()->advertiser()->create();
        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'Empty',
            'status' => 'active',
        ]);

        Sanctum::actingAs($advertiser);

        $this->getJson('/api/advertiser/campaigns/'.$campaign->id.'/impressions?date_from=2026-01-01&date_to=2026-01-31')
            ->assertNotFound();
    }

    public function test_other_advertiser_cannot_view_impressions(): void
    {
        $owner = User::factory()->advertiser()->create();
        $intruder = User::factory()->advertiser()->create();
        $campaign = Campaign::query()->create([
            'advertiser_id' => $owner->id,
            'name' => 'Private',
            'status' => 'active',
        ]);

        CampaignImpressionStat::query()->create([
            'campaign_id' => $campaign->id,
            'date_from' => '2026-02-01',
            'date_to' => '2026-02-28',
            'vehicles_count' => 1,
            'driving_impressions' => 1,
            'parking_impressions' => 0,
            'total_gross_impressions' => 1,
            'campaign_price' => 0,
            'cpm' => null,
            'calculation_version' => 'v1.0',
            'mobility_data_version' => 'x',
            'coefficients_version' => 'v1.0',
            'telemetry_sampling_seconds' => 10,
            'input_fingerprint' => str_repeat('b', 64),
            'matched_direct_count' => 0,
            'matched_fallback_count' => 0,
            'unmatched_count' => 0,
        ]);

        Sanctum::actingAs($intruder);

        $this->getJson('/api/advertiser/campaigns/'.$campaign->id.'/impressions?date_from=2026-02-01&date_to=2026-02-28')
            ->assertForbidden();
    }
}
