<?php

namespace Tests\Unit;

use App\Services\Telemetry\TelemetrySchedulerConfig;
use Carbon\Carbon;
use Tests\TestCase;

class TelemetrySchedulerConfigDailySlotTest extends TestCase
{
    public function test_morning_slot_not_met_before_today_time(): void
    {
        $t = Carbon::parse('2026-01-02 00:05:00', 'UTC');

        $this->assertFalse(TelemetrySchedulerConfig::utcDailySlotMetForYesterday($t, '01:10'));
    }

    public function test_morning_slot_met_after_today_time(): void
    {
        $t = Carbon::parse('2026-01-02 02:00:00', 'UTC');

        $this->assertTrue(TelemetrySchedulerConfig::utcDailySlotMetForYesterday($t, '01:10'));
    }

    public function test_evening_slot_met_after_yesterday_time_before_today_time(): void
    {
        $t = Carbon::parse('2026-01-02 00:05:00', 'UTC');

        $this->assertTrue(TelemetrySchedulerConfig::utcDailySlotMetForYesterday($t, '23:50'));
    }

    public function test_noon_wall_time_uses_yesterday_anchor(): void
    {
        $t = Carbon::parse('2026-01-01 12:00:00', 'UTC');

        $this->assertTrue(TelemetrySchedulerConfig::utcDailySlotMetForYesterday($t, '23:50'));
    }
}
