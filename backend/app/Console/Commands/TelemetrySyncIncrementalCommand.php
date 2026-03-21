<?php

namespace App\Console\Commands;

use App\Services\Telemetry\ClickHouseLocationCollector;
use Illuminate\Console\Command;

class TelemetrySyncIncrementalCommand extends Command
{
    protected $signature = 'telemetry:sync-incremental {--limit= : Max rows (default: TELEMETRY_CH_GLOBAL_INCREMENTAL_ROWS)}';

    protected $description = 'Pull new rows from ClickHouse into PostgreSQL (incremental cursor).';

    public function handle(ClickHouseLocationCollector $collector): int
    {
        if (! $collector->isEnabled()) {
            $this->warn('Set TELEMETRY_CLICKHOUSE_ENABLED=true and ClickHouse URL/credentials in .env');

            return self::SUCCESS;
        }

        $raw = $this->option('limit');
        $limit = ($raw !== null && $raw !== '') ? (int) $raw : null;
        $n = $collector->syncIncremental($limit);
        $this->info("Imported {$n} location row(s).");

        return self::SUCCESS;
    }
}
