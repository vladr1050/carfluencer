<?php

namespace Tests\Unit;

use App\Services\Reports\ReportHeatmapExportPointFilter;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ReportHeatmapExportPointFilterTest extends TestCase
{
    public function test_drops_null_island_and_equator_glitch(): void
    {
        Config::set('reports.heatmap_export.clip_to_bounds', false);

        $out = ReportHeatmapExportPointFilter::filter([
            ['lat' => 0.0, 'lng' => 0.0, 'intensity' => 1],
            ['lat' => 0.5, 'lng' => 0.0, 'intensity' => 1],
            ['lat' => 56.95, 'lng' => 24.11, 'intensity' => 0.5],
        ]);

        $this->assertCount(2, $out);
        $this->assertSame(56.95, $out[1]['lat']);
    }

    public function test_keeps_riga_inside_baltic_bounds(): void
    {
        Config::set('reports.heatmap_export.clip_to_bounds', true);

        $out = ReportHeatmapExportPointFilter::filter([
            ['lat' => 56.95, 'lng' => 24.11, 'intensity' => 1],
        ]);

        $this->assertCount(1, $out);
    }

    public function test_drops_outside_baltic_bounds(): void
    {
        Config::set('reports.heatmap_export.clip_to_bounds', true);

        $out = ReportHeatmapExportPointFilter::filter([
            ['lat' => 60.17, 'lng' => 24.94, 'intensity' => 1],
        ]);

        $this->assertCount(0, $out);
    }
}
