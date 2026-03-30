<?php

namespace App\Console\Commands;

use App\Models\PlatformSetting;
use App\Models\TelemetrySyncEvent;
use App\Services\Telemetry\ClickHouseLocationCollector;
use App\Services\Telemetry\TelemetrySchedulerConfig;
use App\Services\Telemetry\TelemetrySyncEventRecorder;
use App\Services\Telemetry\TelemetrySyncImeiResolver;
use App\Services\Telemetry\TelemetryVehicleSyncState;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TelemetrySchedulerTickCommand extends Command
{
    protected $signature = 'telemetry:scheduler-tick';

    protected $description = 'Run due telemetry tasks (incremental sync, daily analytics, heatmap_cells_daily rollup) using admin-configured intervals.';

    public function handle(): int
    {
        $this->maybePruneSyncEvents();

        if (! config('telemetry.clickhouse.enabled')) {
            return self::SUCCESS;
        }

        $lock = Cache::lock('telemetry:scheduler-tick', 50);
        if (! $lock->get()) {
            return self::SUCCESS;
        }

        try {
            $this->maybeRunIncremental();
            $this->maybeRunDailyJobs();
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }

    private function maybePruneSyncEvents(): void
    {
        Cache::remember('telemetry:sync-events-prune-hourly', 3600, function (): true {
            if (Schema::hasTable('telemetry_sync_events')) {
                DB::table('telemetry_sync_events')
                    ->where('occurred_at', '<', now('UTC')->subHours(48))
                    ->delete();
            }

            return true;
        });
    }

    private function maybeRunIncremental(): void
    {
        $interval = TelemetrySchedulerConfig::incrementalIntervalMinutes();
        $last = PlatformSetting::get(TelemetrySchedulerConfig::KEY_LAST_INCREMENTAL_RUN);
        if ($last !== null && $last !== '') {
            try {
                $lastAt = Carbon::parse($last, 'UTC');
            } catch (\Throwable) {
                $lastAt = null;
            }
            if ($lastAt !== null && now('UTC')->diffInMinutes($lastAt) < $interval) {
                // Silent: with everyMinute() schedule, logging each skip would flood telemetry_sync_events.
                return;
            }
        }

        $resolver = app(TelemetrySyncImeiResolver::class);
        $collector = app(ClickHouseLocationCollector::class);
        $syncState = app(TelemetryVehicleSyncState::class);

        $imeis = $resolver->orderedImeisForScheduledIncrementalPull();
        $maxPerTick = (int) config('telemetry.clickhouse.max_imeis_per_scheduler_tick', 35);
        if ($maxPerTick > 0) {
            $imeis = array_slice($imeis, 0, $maxPerTick);
        }

        if ($imeis === []) {
            Cache::remember('telemetry:incremental-no-pull-targets-notice', 3600, function (): true {
                TelemetrySyncEventRecorder::record(
                    TelemetrySyncEvent::SOURCE_SCHEDULER,
                    TelemetrySyncEvent::ACTION_INCREMENTAL_SKIPPED,
                    TelemetrySyncEvent::STATUS_SKIPPED,
                    'Scheduler: incremental pull skipped — no vehicles with **Scheduled ClickHouse pull** enabled (Fleet → vehicle).',
                    [],
                );

                return true;
            });

            return;
        }

        $pauseUs = ((int) config('telemetry.clickhouse.pause_ms_between_imei', 300)) * 1000;
        $n = 0;
        $lastIdx = count($imeis) - 1;
        $failures = [];
        foreach ($imeis as $idx => $imei) {
            try {
                $n += $collector->syncIncrementalForImei($imei);
                $syncState->markIncrementalSuccessForImeis([$imei]);
            } catch (\Throwable $e) {
                $syncState->recordFailureForImeis([$imei], $e);
                $failures[] = [
                    'imei' => $imei,
                    'message' => $e->getMessage(),
                ];
            }
            if ($pauseUs > 0 && $idx < $lastIdx) {
                usleep($pauseUs);
            }
        }

        PlatformSetting::set(
            TelemetrySchedulerConfig::KEY_LAST_INCREMENTAL_RUN,
            now('UTC')->toIso8601String()
        );
        $this->line("Ran incremental telemetry sync ({$n} row(s), ".count($imeis).' IMEI(s) this tick).');

        $status = TelemetrySyncEvent::STATUS_SUCCESS;
        if ($failures !== []) {
            $status = count($failures) === count($imeis) && $imeis !== []
                ? TelemetrySyncEvent::STATUS_FAILED
                : TelemetrySyncEvent::STATUS_PARTIAL;
        }

        TelemetrySyncEventRecorder::record(
            TelemetrySyncEvent::SOURCE_SCHEDULER,
            TelemetrySyncEvent::ACTION_INCREMENTAL_PULL,
            $status,
            'Scheduler: incremental ClickHouse → PostgreSQL ('.$n.' rows, '.count($imeis).' IMEI(s)).',
            [
                'imeis' => $imeis,
                'rows' => $n,
                'failures' => $failures,
            ],
            $failures !== [] ? implode('; ', array_column($failures, 'message')) : null,
        );
    }

    private function maybeRunDailyJobs(): void
    {
        $nowUtc = now('UTC');
        $yesterday = Carbon::yesterday('UTC')->toDateString();

        if (TelemetrySchedulerConfig::utcDailySlotMetForYesterday($nowUtc, TelemetrySchedulerConfig::buildSessionsAt())) {
            $key = 'telemetry_tick_build_sessions_'.$yesterday;
            if (! Cache::has($key)) {
                Artisan::call('telemetry:build-stop-sessions', ['--date' => $yesterday]);
                Cache::put($key, true, 86_400);
                $this->line('Ran telemetry:build-stop-sessions for '.$yesterday);
                TelemetrySyncEventRecorder::record(
                    TelemetrySyncEvent::SOURCE_SCHEDULER,
                    TelemetrySyncEvent::ACTION_BUILD_STOP_SESSIONS,
                    TelemetrySyncEvent::STATUS_SUCCESS,
                    'Scheduler: rebuilt stop/driving sessions for '.$yesterday.' (UTC).',
                    ['date' => $yesterday],
                );
            }
        }

        if (TelemetrySchedulerConfig::utcDailySlotMetForYesterday($nowUtc, TelemetrySchedulerConfig::aggregateDailyAt())) {
            $key = 'telemetry_tick_aggregate_'.$yesterday;
            if (! Cache::has($key)) {
                Artisan::call('telemetry:aggregate-daily', ['--date' => $yesterday]);
                Cache::put($key, true, 86_400);
                $this->line('Ran telemetry:aggregate-daily for '.$yesterday);
                TelemetrySyncEventRecorder::record(
                    TelemetrySyncEvent::SOURCE_SCHEDULER,
                    TelemetrySyncEvent::ACTION_AGGREGATE_DAILY,
                    TelemetrySyncEvent::STATUS_SUCCESS,
                    'Scheduler: daily impressions aggregate for '.$yesterday.' (UTC).',
                    ['date' => $yesterday],
                );
            }

            $keyHeatmap = 'telemetry_tick_heatmap_rollup_'.$yesterday;
            if (! Cache::has($keyHeatmap) && DB::getDriverName() === 'pgsql') {
                try {
                    Artisan::call('heatmap:aggregate', [
                        '--from' => $yesterday,
                        '--to' => $yesterday,
                        '--all-modes' => true,
                    ]);
                    $this->line('Ran heatmap:aggregate (heatmap_cells_daily) for '.$yesterday);
                    TelemetrySyncEventRecorder::record(
                        TelemetrySyncEvent::SOURCE_SCHEDULER,
                        TelemetrySyncEvent::ACTION_HEATMAP_ROLLUP,
                        TelemetrySyncEvent::STATUS_SUCCESS,
                        'Scheduler: heatmap_cells_daily rollup for '.$yesterday.' (UTC).',
                        ['date' => $yesterday],
                    );
                } catch (\Throwable $e) {
                    $this->warn('heatmap:aggregate failed: '.$e->getMessage());
                    TelemetrySyncEventRecorder::record(
                        TelemetrySyncEvent::SOURCE_SCHEDULER,
                        TelemetrySyncEvent::ACTION_HEATMAP_ROLLUP,
                        TelemetrySyncEvent::STATUS_FAILED,
                        'Scheduler: heatmap rollup failed for '.$yesterday.'.',
                        ['date' => $yesterday],
                        $e->getMessage(),
                    );
                } finally {
                    Cache::put($keyHeatmap, true, 86_400);
                }
            }
        }
    }
}
