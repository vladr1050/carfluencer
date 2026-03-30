<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Services\Telemetry\TelemetrySchedulerConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetrySchedulerTickCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_incremental_does_not_advance_last_run_when_no_pull_enabled_vehicles(): void
    {
        config(['telemetry.clickhouse.enabled' => true]);

        $this->artisan('telemetry:scheduler-tick')->assertSuccessful();

        $this->assertNull(PlatformSetting::get(TelemetrySchedulerConfig::KEY_LAST_INCREMENTAL_RUN));
    }

    public function test_incremental_respects_interval_when_last_run_recent(): void
    {
        config(['telemetry.clickhouse.enabled' => true]);
        PlatformSetting::set(TelemetrySchedulerConfig::KEY_INCREMENTAL_INTERVAL, '60');
        $t = now('UTC')->toIso8601String();
        PlatformSetting::set(TelemetrySchedulerConfig::KEY_LAST_INCREMENTAL_RUN, $t);

        $this->artisan('telemetry:scheduler-tick')->assertSuccessful();

        $this->assertSame($t, PlatformSetting::get(TelemetrySchedulerConfig::KEY_LAST_INCREMENTAL_RUN));
    }
}
