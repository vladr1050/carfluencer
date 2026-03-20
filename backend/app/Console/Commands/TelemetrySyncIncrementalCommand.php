<?php

namespace App\Console\Commands;

use App\Services\Telemetry\ClickHouseLocationCollector;
use Illuminate\Console\Command;

class TelemetrySyncIncrementalCommand extends Command
{
    protected $signature = 'telemetry:sync-incremental {--limit=100000 : Max rows per run}';

    protected $description = 'Pull new rows from ClickHouse into PostgreSQL (incremental cursor).';

    public function handle(ClickHouseLocationCollector $collector): int
    {
        if (! $collector->isEnabled()) {
            $this->warn('Set TELEMETRY_CLICKHOUSE_ENABLED=true and ClickHouse URL/credentials in .env');

            return self::SUCCESS;
        }

        $n = $collector->syncIncremental((int) $this->option('limit'));
        $this->info("Imported {$n} location row(s).");

        return self::SUCCESS;
    }
}
