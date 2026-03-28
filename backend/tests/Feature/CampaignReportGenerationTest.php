<?php

namespace Tests\Feature;

use App\Enums\CampaignReportStatus;
use App\Jobs\GenerateCampaignReportJob;
use App\Models\Campaign;
use App\Models\CampaignReport;
use App\Models\CampaignVehicle;
use App\Models\DailyImpression;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CampaignReportGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_produces_done_status_pdf_and_snapshot_with_fake_browser(): void
    {
        $advertiser = User::factory()->advertiser()->create();
        $admin = User::factory()->admin()->create();
        $mo = User::factory()->mediaOwner()->create();

        $vehicle = Vehicle::query()->create([
            'media_owner_id' => $mo->id,
            'brand' => 'T',
            'model' => 'M',
            'year' => 2024,
            'color_key' => 'black',
            'quantity' => 1,
            'imei' => '999999999999991',
            'status' => 'active',
        ]);

        $campaign = Campaign::query()->create([
            'advertiser_id' => $advertiser->id,
            'name' => 'Report campaign',
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

        DailyImpression::query()->create([
            'stat_date' => '2026-03-10',
            'campaign_id' => $campaign->id,
            'vehicle_id' => $vehicle->id,
            'impressions' => 100,
            'driving_distance_km' => 5,
            'parking_minutes' => 30,
        ]);

        $report = CampaignReport::query()->create([
            'campaign_id' => $campaign->id,
            'advertiser_id' => $advertiser->id,
            'title' => null,
            'report_type' => 'single_period',
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'status' => CampaignReportStatus::Queued,
            'include_driving_heatmap' => true,
            'include_parking_heatmap' => true,
            'created_by' => $admin->id,
        ]);

        $job = new GenerateCampaignReportJob($report->id);
        app()->call([$job, 'handle']);

        $report->refresh();
        $this->assertSame(CampaignReportStatus::Done, $report->status);
        $this->assertNotNull($report->file_path);
        $this->assertNotNull($report->report_data_json);
        $this->assertSame(2, $report->report_data_json['schema_version']);
        $this->assertArrayHasKey('heatmap_pngs', $report->report_data_json['assets']);
        $this->assertCount(3, $report->report_data_json['assets']['heatmap_pngs']['driving']);
        $this->assertCount(3, $report->report_data_json['assets']['heatmap_pngs']['parking']);
        $this->assertSame([$vehicle->id], $report->report_data_json['vehicle_ids']);
        $json = $report->report_data_json;
        $analytics = $json['analytics_snapshot'];
        // Как на портале: 1 vehicle × 31 inclusive day (Mar 1–31) × default 1.0 trips/vehicle/day
        $this->assertSame(31, $analytics['kpis']['carfluencers']);
        $this->assertSame(100, $analytics['kpis']['impressions']);
        $this->assertArrayHasKey('analytics_snapshot', $json);
        $this->assertSame('v1', $analytics['meta']['schema_version']);
        $this->assertSame(100, $analytics['kpis']['impressions']);
        $this->assertSame('daily_impressions', $analytics['meta']['data_source']);
        // BC: top-level kpis mirrors legacy getKpis() key names, values copied from analytics_snapshot only.
        $this->assertSame($analytics['kpis']['impressions'], $json['kpis']['impressions']);
        $this->assertSame($analytics['kpis']['carfluencers'], $json['kpis']['carfluencers']);
        $this->assertSame($analytics['kpis']['km_driven'], $json['kpis']['driving_distance_km']);
        $this->assertSame($analytics['kpis']['driving_hours'], $json['kpis']['driving_time_hours']);
        $this->assertSame($analytics['kpis']['parking_hours'], $json['kpis']['parking_time_hours']);
        $this->assertSame($analytics['meta']['data_source'], $json['kpis']['data_source']);
        $this->assertSame($analytics['meta']['is_estimated'], $json['kpis']['is_estimated']);
        $this->assertArrayHasKey('insights', $analytics);
        $this->assertIsString($analytics['insights']['summary']);

        $this->assertFileExists(Storage::disk('local')->path($report->file_path));
    }
}
