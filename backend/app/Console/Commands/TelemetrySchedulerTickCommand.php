<?php

namespace App\Console\Commands;

use App\Models\PlatformSetting;
use App\Services\Telemetry\ClickHouseLocationCollector;
use App\Services\Telemetry\TelemetrySchedulerConfig;
use App\Services\Telemetry\TelemetrySyncImeiResolver;
use App\Services\Telemetry\TelemetryVehicleSyncState;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TelemetrySchedulerTickCommand extends Command
{
    protected $signature = 'telemetry:scheduler-tick';

    protected $description = 'Run due telemetry tasks (incremental sync, daily analytics, heatmap_cells_daily rollup) using admin-configured intervals.';

    public function handle(): int
    {
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

        $pauseUs = ((int) config('telemetry.clickhouse.pause_ms_between_imei', 300)) * 1000;
        $n = 0;
        $lastIdx = count($imeis) - 1;
        foreach ($imeis as $idx => $imei) {
            try {
                $n += $collector->syncIncrementalForImei($imei);
                $syncState->markIncrementalSuccessForImeis([$imei]);
            } catch (\Throwable $e) {
                $syncState->recordFailureForImeis([$imei], $e);
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
            }
        }

        if (TelemetrySchedulerConfig::utcDailySlotMetForYesterday($nowUtc, TelemetrySchedulerConfig::aggregateDailyAt())) {
            $key = 'telemetry_tick_aggregate_'.$yesterday;
            if (! Cache::has($key)) {
                Artisan::call('telemetry:aggregate-daily', ['--date' => $yesterday]);
                Cache::put($key, true, 86_400);
                $this->line('Ran telemetry:aggregate-daily for '.$yesterday);
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
                } catch (\Throwable $e) {
                    $this->warn('heatmap:aggregate failed: '.$e->getMessage());
                } finally {
                    Cache::put($keyHeatmap, true, 86_400);
                }
            }
        }
    }
}
