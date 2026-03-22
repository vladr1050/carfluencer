<?php

namespace Tests\Unit;

use App\Services\Telemetry\HeatmapBucketIntensity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeatmapBucketIntensityTest extends TestCase
{
    use RefreshDatabase;

    public function test_gamma_one_is_linear(): void
    {
        config(['telemetry.heatmap.intensity_gamma' => 1.0]);

        $this->assertEqualsWithDelta(0.5, HeatmapBucketIntensity::normalize(5, 10), 1e-9);
        $this->assertEqualsWithDelta(1.0, HeatmapBucketIntensity::normalize(10, 10), 1e-9);
    }

    public function test_gamma_above_one_pulls_mids_down_peaks_stay_one(): void
    {
        config(['telemetry.heatmap.intensity_gamma' => 2.0]);

        $this->assertEqualsWithDelta(1.0, HeatmapBucketIntensity::normalize(10, 10), 1e-9);
        $this->assertEqualsWithDelta(0.25, HeatmapBucketIntensity::normalize(5, 10), 1e-9);
    }
}
