<?php

namespace App\Jobs;

use App\Services\Telemetry\HeatmapAggregationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rebuilds {@see HeatmapAggregationService} rollups for a UTC date range after ClickHouse → PostgreSQL import.
 */
final class HeatmapAggregateRangeAfterTelemetryJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public function __construct(
        public string $dateFromYmd,
        public string $dateToYmd,
        public bool $rollingCoalesceLock = false,
    ) {}

    public function handle(HeatmapAggregationService $aggregation): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            Log::info('HeatmapAggregateRangeAfterTelemetryJob skipped (not PostgreSQL).');

            return;
        }

        $lockKey = $this->rollingCoalesceLock
            ? 'heatmap_agg_after_ch_incremental'
            : 'heatmap_agg_after_ch:'.$this->dateFromYmd.':'.$this->dateToYmd;

        $lock = Cache::lock($lockKey, 600);

        if (! $lock->get()) {
            return;
        }

        try {
            $aggregation->aggregateRange($this->dateFromYmd, $this->dateToYmd, null, null);
            Log::info('Heatmap rollup after ClickHouse import finished.', [
                'from' => $this->dateFromYmd,
                'to' => $this->dateToYmd,
                'rolling' => $this->rollingCoalesceLock,
            ]);
        } catch (Throwable $e) {
            Log::error('HeatmapAggregateRangeAfterTelemetryJob failed.', [
                'from' => $this->dateFromYmd,
                'to' => $this->dateToYmd,
                'exception' => $e,
            ]);

            throw $e;
        } finally {
            $lock->release();
        }
    }
}
