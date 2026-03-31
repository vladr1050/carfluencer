<?php

namespace App\Services\ImpressionEngine;

final class ImpressionPeakHour
{
    public static function isPeakHour(int $hourLocal): bool
    {
        /** @var list<array{start: int, end: int}> $windows */
        $windows = config('impression_engine.calculation.peak_hours', []);

        foreach ($windows as $w) {
            if ($hourLocal >= $w['start'] && $hourLocal <= $w['end']) {
                return true;
            }
        }

        return false;
    }
}
