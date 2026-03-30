<?php

namespace App\Services\Reports;

/**
 * PDF/report display: parking duration stored as whole minutes → hours label.
 */
final class ReportParkingMinutesAsHours
{
    public static function format(int $minutes, int $decimals = 2): string
    {
        $hours = $minutes / 60.0;

        return number_format($hours, $decimals, '.', '').' h';
    }
}
