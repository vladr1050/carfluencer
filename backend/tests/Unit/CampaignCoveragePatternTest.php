<?php

namespace Tests\Unit;

use App\Services\Analytics\CampaignCoverageService;
use App\Services\Telemetry\HeatmapAggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Threshold semantics: ratio <= focused_max → focused; <= balanced_max → balanced; else wide.
 */
class CampaignCoveragePatternTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_devices_yields_null_pattern_and_zero_ratio(): void
    {
        Config::set('reports.coverage.patterns.focused_max', 0.20);
        Config::set('reports.coverage.patterns.balanced_max', 0.50);
        Config::set('reports.heatmap_export.bounds', [
            'south' => 56.0,
            'north' => 56.002,
            'west' => 24.0,
            'east' => 24.002,
        ]);

        $svc = new CampaignCoverageService;

        $r0 = $svc->buildCoverage('2026-03-01', '2026-03-07', [], 12);
        $this->assertSame(0, $r0['unique_cells']);
        $this->assertSame(9, $r0['reference_cells']);
        $this->assertSame(0.0, $r0['coverage_ratio']);
        $this->assertNull($r0['coverage_pattern']);
        $this->assertSame('operational_bounds_grid', $r0['denominator_scope']);
    }

    public function test_two_distinct_cells_classifies_against_ratio_thresholds(): void
    {
        Config::set('reports.coverage.patterns.focused_max', 0.30);
        Config::set('reports.coverage.patterns.balanced_max', 0.50);
        Config::set('reports.heatmap_export.bounds', [
            'south' => 56.0,
            'north' => 56.002,
            'west' => 24.0,
            'east' => 24.002,
        ]);

        $tier = 1;
        $imei = '863540060109998';
        $now = now();
        foreach ([[56.0, 24.0], [56.001, 24.0]] as [$lat, $lng]) {
            DB::table('heatmap_cells_daily')->insert([
                'day' => '2026-03-10',
                'mode' => HeatmapAggregationService::MODE_DRIVING,
                'zoom_tier' => $tier,
                'lat_bucket' => $lat,
                'lng_bucket' => $lng,
                'device_id' => $imei,
                'samples_count' => 10,
                'weight_value' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $svc = new CampaignCoverageService;
        $r = $svc->buildCoverage('2026-03-01', '2026-03-31', [$imei], 12);

        $this->assertSame(2, $r['unique_cells']);
        $this->assertSame(9, $r['reference_cells']);
        $this->assertEqualsWithDelta(2 / 9, $r['coverage_ratio'], 0.0001);
        $this->assertSame('focused', $r['coverage_pattern']);
    }
}
