<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignVehicle;
use App\Models\DailyImpression;
use App\Models\DeviceLocation;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Telemetry\DailyImpressionAggregateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetryCampaignScopedAggregateTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregate_for_single_campaign_leaves_other_campaign_rows_intact(): void
    {
        $advertiser = User::factory()->advertiser()->create();
        $mediaOwner = User::factory()->mediaOwner()->create();

        $v1 = Vehicle::query()->create([
            'media_owner_id' => $mediaOwner->id,
            'brand' => 'A',
            'model' => 'One',
            'year' => 2024,
            'color_key' => 'black',
            'quantity' => 1,
            'imei' => '111111111111111',
            'status' => 'active',
        ]);
        $v2 = Vehicle::query()->create([
            'media_owner_id' => $mediaOwner->id,
            'brand' => 'B',
            'model' => 'Two',
            'year' => 2024,
            'color_key' => 'white',
            'quantity' => 1,
            'imei' => '222222222222222',
            'status' => 'active',
        ]);

        $c1 = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'C1',
            'status' => 'active',
            'created_by_admin' => false,
            'platform_commission_percent' => '10',
            'agency_commission_percent' => '0',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
        $c2 = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'C2',
            'status' => 'active',
            'created_by_admin' => false,
            'platform_commission_percent' => '10',
            'agency_commission_percent' => '0',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);

        CampaignVehicle::query()->create([
            'campaign_id' => $c1->id,
            'vehicle_id' => $v1->id,
            'placement_size_class' => 'M',
            'status' => 'active',
        ]);
        CampaignVehicle::query()->create([
            'campaign_id' => $c2->id,
            'vehicle_id' => $v2->id,
            'placement_size_class' => 'M',
            'status' => 'active',
        ]);

        $day = Carbon::parse('2026-03-20 10:00:00', 'UTC');
        foreach ([$v1->imei, $v2->imei] as $imei) {
            for ($i = 0; $i < 3; $i++) {
                DeviceLocation::query()->create([
                    'device_id' => $imei,
                    'event_at' => $day->copy()->addMinutes($i),
                    'latitude' => 51.505,
                    'longitude' => -0.09,
                    'speed' => 0,
                    'battery' => null,
                    'gsm_signal' => null,
                    'odometer' => null,
                    'ignition' => null,
                    'extra_json' => null,
                ]);
            }
        }

        $svc = app(DailyImpressionAggregateService::class);
        $svc->aggregateForDate($day->copy()->startOfDay());

        $this->assertSame(2, DailyImpression::query()->whereDate('stat_date', $day->toDateString())->count());

        DailyImpression::query()->where('campaign_id', $c2->id)->update(['impressions' => 99_999]);

        $svc->aggregateForDate($day->copy()->startOfDay(), $c1->id);

        $this->assertSame(99_999, (int) DailyImpression::query()->where('campaign_id', $c2->id)->value('impressions'));
        $this->assertNotSame(99_999, (int) DailyImpression::query()->where('campaign_id', $c1->id)->value('impressions'));
    }

    public function test_build_stop_sessions_unknown_campaign_fails(): void
    {
        $this->artisan('telemetry:build-stop-sessions', [
            '--date' => '2026-01-01',
            '--campaign' => '999999',
        ])->assertFailed();
    }

    public function test_aggregate_daily_unknown_campaign_fails(): void
    {
        $this->artisan('telemetry:aggregate-daily', [
            '--date' => '2026-01-01',
            '--campaign' => '999999',
        ])->assertFailed();
    }
}
