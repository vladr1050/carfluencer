<?php

namespace App\Console\Commands;

use App\Services\Telemetry\DailyImpressionAggregateService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TelemetryAggregateDailyCommand extends Command
{
    protected $signature = 'telemetry:aggregate-daily
                            {--date= : Calendar day (YYYY-MM-DD), default yesterday}';

    protected $description = 'Rebuild daily_impressions and daily_zone_impressions for a day.';

    public function handle(DailyImpressionAggregateService $aggregator): int
    {
        $opt = $this->option('date');
        $date = is_string($opt) && $opt !== ''
            ? Carbon::parse($opt, 'UTC')->startOfDay()
            : Carbon::yesterday('UTC')->startOfDay();

        $aggregator->aggregateForDate($date);
        $this->info("Aggregated telemetry for {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
