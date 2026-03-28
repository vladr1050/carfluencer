<?php

namespace Tests\Unit;

use App\Services\Reports\ReportHeatmapGradientColor;
use Tests\TestCase;

class ReportHeatmapGradientColorTest extends TestCase
{
    public function test_endpoints_and_mid(): void
    {
        $g = ['0' => '#CE93D8', '1' => '#B71C1C'];
        $this->assertSame('#CE93D8', ReportHeatmapGradientColor::at(0.0, $g));
        $this->assertSame('#B71C1C', ReportHeatmapGradientColor::at(1.0, $g));
        $mid = ReportHeatmapGradientColor::at(0.5, $g);
        $this->assertStringStartsWith('#', $mid);
    }
}
