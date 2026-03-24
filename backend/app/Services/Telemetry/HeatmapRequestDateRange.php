<?php

namespace App\Services\Telemetry;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Optional max calendar-day span for heatmap APIs (avoids timeouts on huge raw scans).
 */
final class HeatmapRequestDateRange
{
    public static function assertWithinConfiguredLimit(?string $dateFrom, ?string $dateTo): void
    {
        $max = config('telemetry.heatmap.max_date_range_days');
        if ($max === null || $dateFrom === null || $dateTo === null || $dateFrom === '' || $dateTo === '') {
            return;
        }

        $start = Carbon::parse($dateFrom)->startOfDay();
        $end = Carbon::parse($dateTo)->startOfDay();
        $days = (int) $start->diffInDays($end) + 1;
        if ($days > $max) {
            throw ValidationException::withMessages([
                'date_to' => [__('Heatmap date range may not exceed :days calendar days.', ['days' => $max])],
            ]);
        }
    }
}
