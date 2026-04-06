<?php

namespace App\Services\Telemetry;

use App\Jobs\HeatmapAggregateRangeAfterTelemetryJob;
use App\Models\DeviceLocation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Queues heatmap_cells_daily rebuild after telemetry landed in {@see DeviceLocation}.
 */
final class HeatmapRollupAfterTelemetryImport
{
    public static function enabled(): bool
    {
        return filter_var(
            config('telemetry.heatmap.rollup.after_clickhouse_import.enabled', true),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * After historical / window backfill with known inclusive Y-m-d bounds (UTC).
     */
    public static function dispatchForHistoricalWindow(string $dateFromYmd, string $dateToYmd): void
    {
        if (! self::enabled()) {
            return;
        }

        $from = Carbon::parse($dateFromYmd, 'UTC')->startOfDay();
        $to = Carbon::parse($dateToYmd, 'UTC')->startOfDay();
        if ($to->lt($from)) {
            return;
        }

        $days = (int) $from->diffInDays($to) + 1;
        $max = (int) config(
            'telemetry.heatmap.rollup.after_clickhouse_import.max_historical_range_days_for_auto_job',
            366
        );

        if ($days > $max) {
            Log::warning('Automatic heatmap rollup after ClickHouse import skipped: range exceeds max_historical_range_days_for_auto_job.', [
                'date_from' => $dateFromYmd,
                'date_to' => $dateToYmd,
                'days' => $days,
                'max' => $max,
            ]);

            return;
        }

        HeatmapAggregateRangeAfterTelemetryJob::dispatch(
            $from->toDateString(),
            $to->toDateString(),
            false,
        );
    }

    /**
     * After incremental import: rebuild last N UTC calendar days (today inclusive).
     */
    public static function dispatchRollingAfterIncremental(int $importedRowCount): void
    {
        if (! self::enabled() || $importedRowCount <= 0) {
            return;
        }

        $calendarDays = (int) config(
            'telemetry.heatmap.rollup.after_clickhouse_import.incremental_calendar_days',
            3
        );
        $calendarDays = max(1, min(62, $calendarDays));

        $to = now('UTC')->toDateString();
        $from = now('UTC')->copy()->subDays($calendarDays - 1)->toDateString();

        HeatmapAggregateRangeAfterTelemetryJob::dispatch($from, $to, true);
    }
}
