<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Campaign PDF/heatmap loads the same telemetry windows as the heatmap API.
 * Applies the stricter of {@see config('reports.max_calendar_days')} and
 * {@see config('telemetry.heatmap.max_date_range_days')} when either is set.
 */
final class CampaignReportDateSpan
{
    public static function assertWithinLimits(string $dateFrom, string $dateTo): void
    {
        $limits = array_values(array_filter([
            self::positiveIntOrNull(config('reports.max_calendar_days')),
            self::positiveIntOrNull(config('telemetry.heatmap.max_date_range_days')),
        ], static fn (?int $v): bool => $v !== null));

        if ($limits === []) {
            return;
        }

        $max = min($limits);
        $start = Carbon::parse($dateFrom)->startOfDay();
        $end = Carbon::parse($dateTo)->startOfDay();
        $days = (int) $start->diffInDays($end) + 1;

        if ($days > $max) {
            throw ValidationException::withMessages([
                'date_to' => [__('Campaign report period may not exceed :days calendar days.', ['days' => $max])],
            ]);
        }
    }

    private static function positiveIntOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $n = (int) $value;

        return $n > 0 ? $n : null;
    }
}
