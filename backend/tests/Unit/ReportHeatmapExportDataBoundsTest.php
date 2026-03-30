<?php

namespace Tests\Unit;

use App\Services\Reports\ReportHeatmapExportDataBounds;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ReportHeatmapExportDataBoundsTest extends TestCase
{
    private function envelope(): array
    {
        return [
            'min_lat' => 56.80,
            'max_lat' => 57.15,
            'min_lng' => 23.40,
            'max_lng' => 24.50,
        ];
    }

    public function test_disabled_returns_no_data_fit(): void
    {
        Config::set('reports.heatmap_export.data_fit_to_active_cells', false);
        $r = ReportHeatmapExportDataBounds::compute([[56.95, 24.10, 0.5]], $this->envelope());
        $this->assertFalse($r['use_data_fit']);
    }

    public function test_empty_heat_returns_no_data_fit(): void
    {
        Config::set('reports.heatmap_export.data_fit_to_active_cells', true);
        $r = ReportHeatmapExportDataBounds::compute([], $this->envelope());
        $this->assertFalse($r['use_data_fit']);
    }

    public function test_tight_cluster_uses_padded_bounds(): void
    {
        Config::set('reports.heatmap_export.data_fit_to_active_cells', true);
        Config::set('reports.heatmap_export.data_fit_min_points', 2);
        Config::set('reports.heatmap_export.data_fit_padding_ratio', 0.1);
        $heat = [
            [56.950, 24.100, 0.3],
            [56.951, 24.101, 0.4],
        ];
        $r = ReportHeatmapExportDataBounds::compute($heat, $this->envelope());
        $this->assertTrue($r['use_data_fit']);
        $this->assertLessThan(56.950, $r['south']);
        $this->assertGreaterThan(56.951, $r['north']);
        $this->assertArrayHasKey('max_zoom', $r);
    }

    public function test_huge_span_falls_back(): void
    {
        Config::set('reports.heatmap_export.data_fit_to_active_cells', true);
        Config::set('reports.heatmap_export.data_fit_max_lat_span_deg', 0.05);
        $heat = [
            [56.0, 24.0, 1],
            [56.2, 24.2, 1],
        ];
        $r = ReportHeatmapExportDataBounds::compute($heat, $this->envelope());
        $this->assertFalse($r['use_data_fit']);
    }
}
