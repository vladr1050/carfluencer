<?php

namespace Tests\Unit;

use App\Services\Reports\ReportParkingMinutesAsHours;
use Tests\TestCase;

class ReportParkingMinutesAsHoursTest extends TestCase
{
    public function test_formats_minutes_as_hours_with_suffix(): void
    {
        $this->assertSame('0.00 h', ReportParkingMinutesAsHours::format(0));
        $this->assertSame('1.00 h', ReportParkingMinutesAsHours::format(60));
        $this->assertSame('1.50 h', ReportParkingMinutesAsHours::format(90));
        $this->assertSame('0.75 h', ReportParkingMinutesAsHours::format(45));
    }
}
