<?php

namespace App\Console\Commands;

use App\Services\Telemetry\ClickHouseLocationCollector;
use App\Services\Telemetry\HeatmapRollupAfterTelemetryImport;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TelemetrySyncHistoricalCommand extends Command
{
    protected $signature = 'telemetry:sync-historical
                            {--from= : Start datetime (UTC), e.g. 2024-01-01}
                            {--to= : End datetime (UTC), exclusive}
                            {--limit=500000 : Max rows}';

    protected $description = 'Backfill device_locations from ClickHouse for a time window.';

    public function handle(ClickHouseLocationCollector $collector): int
    {
        if (! $collector->isEnabled()) {
            $this->warn('ClickHouse collector is disabled.');

            return self::FAILURE;
        }

        $from = $this->option('from');
        $to = $this->option('to');
        if (! is_string($from) || $from === '' || ! is_string($to) || $to === '') {
            $this->error('Both --from and --to are required.');

            return self::FAILURE;
        }

        $fromC = Carbon::parse($from, 'UTC');
        $toC = Carbon::parse($to, 'UTC');
        $n = $collector->syncHistorical(
            $fromC,
            $toC,
            (int) $this->option('limit')
        );
        $this->info("Imported {$n} location row(s).");

        $toInclusive = $toC->copy()->subMicrosecond();
        if ($toInclusive->gte($fromC)) {
            HeatmapRollupAfterTelemetryImport::dispatchForHistoricalWindow(
                $fromC->toDateString(),
                $toInclusive->toDateString(),
            );
        }

        return self::SUCCESS;
    }
}
