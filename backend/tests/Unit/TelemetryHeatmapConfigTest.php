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

    public function test_trips_kpi_uses_platform_setting_and_full_inclusive_days(): void
    {
        config(['telemetry.heatmap.advertiser_trips_per_vehicle_full_day' => 1.0]);
        PlatformSetting::set(TelemetryHeatmapConfig::KEY_ADVERTISER_TRIPS_PER_VEHICLE_FULL_DAY, '3.2');

        $this->assertEqualsWithDelta(3.2, TelemetryHeatmapConfig::tripsPerVehicleFullDay(), 1e-9);
        $this->assertSame(7, TelemetryHeatmapConfig::fullCalendarDaysInclusive('2026-03-01', '2026-03-07'));

        $kpi = TelemetryHeatmapConfig::computeAdvertiserTripsKpi('2026-03-01', '2026-03-07', 10);
        $this->assertSame(224, $kpi['trips']);
        $this->assertSame(10, $kpi['heatmap_selection']['vehicle_count']);
        $this->assertSame(7, $kpi['heatmap_selection']['full_calendar_days']);
    }
}
