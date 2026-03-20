<?php

namespace App\Console\Commands;

use App\Models\PlatformSetting;
use App\Services\Telemetry\ClickHouseLocationCollector;
use App\Services\Telemetry\TelemetrySchedulerConfig;
use App\Services\Telemetry\TelemetrySyncImeiResolver;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class TelemetrySchedulerTickCommand extends Command
{
    protected $signature = 'telemetry:scheduler-tick';

    protected $description = 'Run due telemetry tasks (incremental sync + daily analytics) using admin-configured intervals.';

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

        $imeis = app(TelemetrySyncImeiResolver::class)->resolve('all_vehicles', null, [], onlyTelemetryPullEnabled: true);
        $n = app(ClickHouseLocationCollector::class)->syncIncrementalForImeis($imeis);
        PlatformSetting::set(
            TelemetrySchedulerConfig::KEY_LAST_INCREMENTAL_RUN,
            now('UTC')->toIso8601String()
        );
        $this->line("Ran incremental telemetry sync for platform vehicles ({$n} row(s), ".count($imeis).' IMEI(s)).');
    }

    private function maybeRunDailyJobs(): void
    {
        $now = now()->format('H:i');
        $yesterday = Carbon::yesterday('UTC')->toDateString();

        if ($now === TelemetrySchedulerConfig::buildSessionsAt()) {
            $key = 'telemetry_tick_build_sessions_'.$yesterday;
            if (! Cache::has($key)) {
                Artisan::call('telemetry:build-stop-sessions', ['--date' => $yesterday]);
                Cache::put($key, true, 86_400);
                $this->line('Ran telemetry:build-stop-sessions for '.$yesterday);
            }
        }

        if ($now === TelemetrySchedulerConfig::aggregateDailyAt()) {
            $key = 'telemetry_tick_aggregate_'.$yesterday;
            if (! Cache::has($key)) {
                Artisan::call('telemetry:aggregate-daily', ['--date' => $yesterday]);
                Cache::put($key, true, 86_400);
                $this->line('Ran telemetry:aggregate-daily for '.$yesterday);
            }
        }
    }
}
