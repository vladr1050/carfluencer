<?php

namespace Tests\Unit;

use App\Services\Reports\CampaignReportDateSpan;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CampaignReportDateSpanTest extends TestCase
{
    public function test_passes_when_no_limits_configured(): void
    {
        Config::set('reports.max_calendar_days', null);
        Config::set('telemetry.heatmap.max_date_range_days', null);

        CampaignReportDateSpan::assertWithinLimits('2025-01-01', '2026-12-31');

        $this->assertTrue(true);
    }

    public function test_uses_stricter_of_report_and_telemetry_limits(): void
    {
        Config::set('reports.max_calendar_days', 10);
        Config::set('telemetry.heatmap.max_date_range_days', 100);

        CampaignReportDateSpan::assertWithinLimits('2026-01-01', '2026-01-10');

        $this->expectException(ValidationException::class);
        CampaignReportDateSpan::assertWithinLimits('2026-01-01', '2026-01-11');
    }
}
