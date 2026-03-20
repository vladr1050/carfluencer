<?php

namespace App\Console\Commands;

use App\Services\Telemetry\StopSessionBuilderService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TelemetryBuildStopSessionsCommand extends Command
{
    protected $signature = 'telemetry:build-stop-sessions
                            {--date= : Calendar day (YYYY-MM-DD), default yesterday}';

    protected $description = 'Build stop_sessions from device_locations (parking vs driving) and run zone attribution.';

    public function handle(StopSessionBuilderService $builder): int
    {
        $opt = $this->option('date');
        $date = is_string($opt) && $opt !== ''
            ? Carbon::parse($opt, 'UTC')->startOfDay()
            : Carbon::yesterday('UTC')->startOfDay();

        $n = $builder->buildForDate($date);
        $this->info("Created {$n} session row(s) for {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
