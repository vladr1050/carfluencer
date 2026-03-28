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

    public function test_log_scales_with_cap085(): void
    {
        $in = [[56.0, 24.0, 0.0], [56.1, 24.1, 1.0]];
        $out = ReportDrivingHeatmapIntensityScaler::scale($in, 'log');
        $this->assertCount(2, $out);
        $this->assertSame(0.0, $out[0][2]);
        $this->assertEqualsWithDelta(0.85, $out[1][2], 0.0001);
    }

    public function test_scale_from_sample_weights_log(): void
    {
        $cells = [
            ['lat' => 56.0, 'lng' => 24.0, 'w' => 10],
            ['lat' => 56.1, 'lng' => 24.1, 'w' => 1000],
        ];
        $out = ReportDrivingHeatmapIntensityScaler::scaleFromSampleWeights($cells, 'log');
        $this->assertLessThan(0.86, $out[1][2]);
        $this->assertGreaterThan($out[0][2], $out[1][2]);
    }

    public function test_log_empty_and_zero_max(): void
    {
        $this->assertSame([], ReportDrivingHeatmapIntensityScaler::scale([], 'log'));
        $out = ReportDrivingHeatmapIntensityScaler::scale([[56.0, 24.0, 0.0]], 'log');
        $this->assertSame(0.0, $out[0][2]);
    }
}
