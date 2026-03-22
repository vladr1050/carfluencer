<?php

namespace Tests\Unit;

use App\Models\PlatformSetting;
use App\Services\Telemetry\TelemetryHeatmapConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetryHeatmapConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_config_when_platform_setting_missing(): void
    {
        config(['telemetry.heatmap.intensity_gamma' => 2.0]);

        $this->assertEqualsWithDelta(2.0, TelemetryHeatmapConfig::intensityGamma(), 1e-9);
    }

    public function test_platform_setting_overrides_config(): void
    {
        config(['telemetry.heatmap.intensity_gamma' => 2.0]);
        PlatformSetting::set(TelemetryHeatmapConfig::KEY_INTENSITY_GAMMA, '1.25');

        $this->assertEqualsWithDelta(1.25, TelemetryHeatmapConfig::intensityGamma(), 1e-9);
    }
}
