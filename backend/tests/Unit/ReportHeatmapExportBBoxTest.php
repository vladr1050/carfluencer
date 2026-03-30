<?php

namespace Tests\Unit;

use App\Services\Reports\ReportHeatmapExportBBox;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ReportHeatmapExportBBoxTest extends TestCase
{
    public function test_operational_envelope_matches_bounds_config(): void
    {
        Config::set('reports.heatmap_export.bounds', [
            'south' => 53.1,
            'north' => 59.2,
            'west' => 20.5,
            'east' => 28.9,
        ]);
        $e = ReportHeatmapExportBBox::operationalEnvelope();
        $this->assertEqualsWithDelta(53.1, $e['min_lat'], 1e-9);
        $this->assertEqualsWithDelta(59.2, $e['max_lat'], 1e-9);
        $this->assertEqualsWithDelta(20.5, $e['min_lng'], 1e-9);
        $this->assertEqualsWithDelta(28.9, $e['max_lng'], 1e-9);
    }
}
