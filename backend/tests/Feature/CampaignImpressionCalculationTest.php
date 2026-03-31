<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignImpressionStat;
use App\Models\DeviceLocation;
use App\Models\MobilityReferenceCell;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ImpressionEngine\CampaignImpressionCalculationService;
use App\Services\ImpressionEngine\Contracts\H3IndexerInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CampaignImpressionCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculation_creates_snapshot_from_device_locations_and_mobility_cell(): void
    {
        config(['impression_engine.calculation.store_exposure_hourly' => false]);

        $h3 = app(H3IndexerInterface::class);
        $lat = 56.9;
        $lng = 24.4;
        $cellId = $h3->latLngToCellId($lat, $lng);

        MobilityReferenceCell::query()->create([
            'cell_id' => $cellId,
            'lat_center' => $lat,
            'lng_center' => $lng,
            'vehicle_aadt' => 864_000,
            'pedestrian_daily' => 2_400,
            'average_speed_kmh' => 50,
            'hourly_peak_factor' => 1.5,
            'data_version' => 'calc_test_v1',
            'records_count' => 1,
        ]);

        $mediaOwner = User::factory()->mediaOwner()->create();
        $vehicle = Vehicle::query()->create([
            'media_owner_id' => $mediaOwner->id,
            'brand' => 'Test',
            'model' => 'Van',
            'imei' => 'IMEI5550001',
            'status' => 'active',
        ]);

        $advertiser = User::factory()->advertiser()->create();
        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'Wrap',
            'status' => 'active',
            'total_price' => 5000,
        ]);
        $campaign->vehicles()->attach($vehicle->id, [
            'placement_size_class' => 'M',
            'agreed_price' => 100,
            'status' => 'active',
        ]);

        $eventAt = CarbonImmutable::parse('2026-03-28 08:30:00', 'Europe/Riga')->utc();
        for ($i = 0; $i < 20; $i++) {
            DeviceLocation::query()->create([
                'device_id' => '5550001',
                'event_at' => $eventAt->addSeconds($i),
                'latitude' => $lat,
                'longitude' => $lng,
                'speed' => 25.0,
            ]);
        }

        $svc = app(CampaignImpressionCalculationService::class);
        $stat = $svc->calculate(
            $campaign,
            '2026-03-28',
            '2026-03-28',
            'calc_test_v1',
            false,
            [$vehicle->id],
        );

        $this->assertGreaterThan(0, $stat->total_gross_impressions);
        $this->assertSame(1, $stat->matched_direct_count);
        $this->assertSame(0, $stat->matched_fallback_count);
        $this->assertDatabaseCount('campaign_impression_stats', 1);
    }

    public function test_calculation_is_idempotent_by_fingerprint(): void
    {
        config(['impression_engine.calculation.store_exposure_hourly' => false]);

        $h3 = app(H3IndexerInterface::class);
        $cellId = $h3->latLngToCellId(56.91, 24.41);

        MobilityReferenceCell::query()->create([
            'cell_id' => $cellId,
            'lat_center' => 56.91,
            'lng_center' => 24.41,
            'vehicle_aadt' => 500_000,
            'pedestrian_daily' => 1_000,
            'average_speed_kmh' => 40,
            'hourly_peak_factor' => 1.2,
            'data_version' => 'calc_test_v2',
            'records_count' => 1,
        ]);

        $mediaOwner = User::factory()->mediaOwner()->create();
        $vehicle = Vehicle::query()->create([
            'media_owner_id' => $mediaOwner->id,
            'brand' => 'T',
            'model' => 'V',
            'imei' => 'X'.Str::upper(Str::random(8)),
            'status' => 'active',
        ]);
        $advertiser = User::factory()->advertiser()->create();
        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'C',
            'status' => 'active',
            'total_price' => 100,
        ]);
        $campaign->vehicles()->attach($vehicle->id, [
            'placement_size_class' => 'M',
            'agreed_price' => 10,
            'status' => 'active',
        ]);

        $digits = preg_replace('/\D+/', '', (string) $vehicle->imei);
        $eventAt = CarbonImmutable::parse('2026-04-01 09:00:00', 'Europe/Riga')->utc();
        DeviceLocation::query()->create([
            'device_id' => $digits,
            'event_at' => $eventAt,
            'latitude' => 56.91,
            'longitude' => 24.41,
            'speed' => 40.0,
        ]);

        $svc = app(CampaignImpressionCalculationService::class);
        $a = $svc->calculate($campaign, '2026-04-01', '2026-04-01', 'calc_test_v2', false, [$vehicle->id]);
        $b = $svc->calculate($campaign, '2026-04-01', '2026-04-01', 'calc_test_v2', false, [$vehicle->id]);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, CampaignImpressionStat::query()->count());
    }

    public function test_force_recalculate_replaces_snapshot_with_same_fingerprint(): void
    {
        config(['impression_engine.calculation.store_exposure_hourly' => false]);

        $h3 = app(H3IndexerInterface::class);
        $cellId = $h3->latLngToCellId(57.0, 24.5);

        MobilityReferenceCell::query()->create([
            'cell_id' => $cellId,
            'lat_center' => 57.0,
            'lng_center' => 24.5,
            'vehicle_aadt' => 400_000,
            'pedestrian_daily' => 800,
            'average_speed_kmh' => 35,
            'hourly_peak_factor' => 1.0,
            'data_version' => 'calc_test_v3',
            'records_count' => 1,
        ]);

        $mediaOwner = User::factory()->mediaOwner()->create();
        $vehicle = Vehicle::query()->create([
            'media_owner_id' => $mediaOwner->id,
            'brand' => 'T',
            'model' => 'V',
            'imei' => 'Y'.Str::upper(Str::random(8)),
            'status' => 'active',
        ]);
        $advertiser = User::factory()->advertiser()->create();
        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'C2',
            'status' => 'active',
            'total_price' => 200,
        ]);
        $campaign->vehicles()->attach($vehicle->id, [
            'placement_size_class' => 'M',
            'agreed_price' => 20,
            'status' => 'active',
        ]);

        $digits = preg_replace('/\D+/', '', (string) $vehicle->imei);
        $eventAt = CarbonImmutable::parse('2026-05-01 10:00:00', 'Europe/Riga')->utc();
        DeviceLocation::query()->create([
            'device_id' => $digits,
            'event_at' => $eventAt,
            'latitude' => 57.0,
            'longitude' => 24.5,
            'speed' => 35.0,
        ]);

        $svc = app(CampaignImpressionCalculationService::class);
        $first = $svc->calculate($campaign, '2026-05-01', '2026-05-01', 'calc_test_v3', false, [$vehicle->id]);
        $second = $svc->calculate($campaign, '2026-05-01', '2026-05-01', 'calc_test_v3', true, [$vehicle->id]);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(1, CampaignImpressionStat::query()->count());
        $this->assertSame($first->input_fingerprint, $second->input_fingerprint);
    }

    public function test_duplicate_fingerprint_backfills_hourly_exposure_when_storage_turned_on(): void
    {
        config(['impression_engine.calculation.store_exposure_hourly' => false]);

        $h3 = app(H3IndexerInterface::class);
        $cellId = $h3->latLngToCellId(56.92, 24.42);

        MobilityReferenceCell::query()->create([
            'cell_id' => $cellId,
            'lat_center' => 56.92,
            'lng_center' => 24.42,
            'vehicle_aadt' => 600_000,
            'pedestrian_daily' => 1_200,
            'average_speed_kmh' => 42,
            'hourly_peak_factor' => 1.1,
            'data_version' => 'calc_test_v4',
            'records_count' => 1,
        ]);

        $mediaOwner = User::factory()->mediaOwner()->create();
        $vehicle = Vehicle::query()->create([
            'media_owner_id' => $mediaOwner->id,
            'brand' => 'T',
            'model' => 'V',
            // Digits required: aggregate() maps device_locations.device_id via stripped IMEI; all-alpha random yields no rows.
            'imei' => 'IMEI555'.Str::upper(Str::random(6)),
            'status' => 'active',
        ]);
        $advertiser = User::factory()->advertiser()->create();
        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'C4',
            'status' => 'active',
            'total_price' => 300,
        ]);
        $campaign->vehicles()->attach($vehicle->id, [
            'placement_size_class' => 'M',
            'agreed_price' => 30,
            'status' => 'active',
        ]);

        $digits = preg_replace('/\D+/', '', (string) $vehicle->imei);
        $eventAt = CarbonImmutable::parse('2026-06-01 11:00:00', 'Europe/Riga')->utc();
        DeviceLocation::query()->create([
            'device_id' => $digits,
            'event_at' => $eventAt,
            'latitude' => 56.92,
            'longitude' => 24.42,
            'speed' => 42.0,
        ]);

        $svc = app(CampaignImpressionCalculationService::class);
        $svc->calculate($campaign, '2026-06-01', '2026-06-01', 'calc_test_v4', false, [$vehicle->id]);

        $this->assertSame(0, (int) DB::table('campaign_vehicle_exposure_hourly')->count());

        config(['impression_engine.calculation.store_exposure_hourly' => true]);
        $svc->calculate($campaign, '2026-06-01', '2026-06-01', 'calc_test_v4', false, [$vehicle->id]);

        $this->assertGreaterThan(0, (int) DB::table('campaign_vehicle_exposure_hourly')->count());
    }
}
