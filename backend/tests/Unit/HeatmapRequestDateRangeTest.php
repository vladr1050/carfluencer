<?php

namespace Tests\Unit;

use App\Services\Telemetry\HeatmapRequestDateRange;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HeatmapRequestDateRangeTest extends TestCase
{
    protected function tearDown(): void
    {
        config(['telemetry.heatmap.max_date_range_days' => null]);
        parent::tearDown();
    }

    public function test_no_limit_when_config_null(): void
    {
        config(['telemetry.heatmap.max_date_range_days' => null]);
        HeatmapRequestDateRange::assertWithinConfiguredLimit('2020-01-01', '2030-01-01');
        $this->assertTrue(true);
    }

    public function test_throws_when_span_exceeds_max(): void
    {
        config(['telemetry.heatmap.max_date_range_days' => 5]);
        $this->expectException(ValidationException::class);
        HeatmapRequestDateRange::assertWithinConfiguredLimit('2026-01-01', '2026-01-10');
    }

    public function test_inclusive_boundary_allowed(): void
    {
        config(['telemetry.heatmap.max_date_range_days' => 5]);
        HeatmapRequestDateRange::assertWithinConfiguredLimit('2026-01-01', '2026-01-05');
        $this->assertTrue(true);
    }
}
