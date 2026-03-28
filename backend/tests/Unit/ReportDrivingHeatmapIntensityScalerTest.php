<?php

namespace Tests\Unit;

use App\Services\Reports\ReportDrivingHeatmapIntensityScaler;
use Tests\TestCase;

class ReportDrivingHeatmapIntensityScalerTest extends TestCase
{
    public function test_linear_passes_through(): void
    {
        $in = [[56.0, 24.0, 0.5], [56.1, 24.1, 1.0]];
        $out = ReportDrivingHeatmapIntensityScaler::scale($in, 'linear');
        $this->assertSame($in, $out);
    }

    public function test_log_scales_to_max_one(): void
    {
        $in = [[56.0, 24.0, 0.0], [56.1, 24.1, 1.0]];
        $out = ReportDrivingHeatmapIntensityScaler::scale($in, 'log');
        $this->assertCount(2, $out);
        $this->assertSame(0.0, $out[0][2]);
        $this->assertSame(1.0, $out[1][2]);
    }

    public function test_log_empty_and_zero_max(): void
    {
        $this->assertSame([], ReportDrivingHeatmapIntensityScaler::scale([], 'log'));
        $out = ReportDrivingHeatmapIntensityScaler::scale([[56.0, 24.0, 0.0]], 'log');
        $this->assertSame(0.0, $out[0][2]);
    }
}
