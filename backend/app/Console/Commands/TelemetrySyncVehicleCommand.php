<?php

namespace App\Console\Commands;

use App\Jobs\SyncVehicleTelemetryFromClickHouseJob;
use App\Models\Vehicle;
use App\Services\Telemetry\ClickHouseLocationCollector;
use App\Services\Telemetry\HeatmapRollupAfterTelemetryImport;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TelemetrySyncVehicleCommand extends Command
{
    protected $signature = 'telemetry:sync-vehicle
                            {vehicle : Vehicle database ID or IMEI}
                            {--incremental : Pull only new points after per-IMEI cursor}
                            {--from= : Historical start date (Y-m-d)}
                            {--to= : Historical end date (Y-m-d, inclusive)}
                            {--sync : Run synchronously instead of queue}';

    protected $description = 'Load ClickHouse locations for one vehicle (IMEI) into PostgreSQL.';

    public function handle(ClickHouseLocationCollector $collector): int
    {
        if (! $collector->isEnabled()) {
            $this->error('Enable TELEMETRY_CLICKHOUSE_ENABLED in .env');

            return self::FAILURE;
        }

        $arg = (string) $this->argument('vehicle');
        $vehicle = ctype_digit($arg)
            ? Vehicle::query()->find((int) $arg)
            : Vehicle::query()->where('imei', $arg)->first();

        if ($vehicle === null) {
            $this->error('Vehicle not found.');

            return self::FAILURE;
        }

        $incremental = (bool) $this->option('incremental');
        $from = $this->option('from');
        $to = $this->option('to');

        if ($incremental) {
            if ($this->option('sync')) {
                $n = $collector->syncIncrementalForImei($vehicle->imei);
                $this->info("Imported {$n} row(s) (incremental).");
                HeatmapRollupAfterTelemetryImport::dispatchRollingAfterIncremental($n);
            } else {
                SyncVehicleTelemetryFromClickHouseJob::dispatch($vehicle->id, 'incremental', null, null);
                $this->info('Queued incremental telemetry sync for vehicle #'.$vehicle->id);
            }

            return self::SUCCESS;
        }

        if (is_string($from) && $from !== '' && is_string($to) && $to !== '') {
            if ($this->option('sync')) {
                $f = Carbon::parse($from, 'UTC')->startOfDay();
                $t = Carbon::parse($to, 'UTC')->endOfDay()->addSecond();
                $n = $collector->syncHistoricalForImei($vehicle->imei, $f, $t);
                $this->info("Imported {$n} row(s) (historical).");
                HeatmapRollupAfterTelemetryImport::dispatchForHistoricalWindow($from, $to);
            } else {
                SyncVehicleTelemetryFromClickHouseJob::dispatch($vehicle->id, 'historical', $from, $to);
                $this->info('Queued historical telemetry sync for vehicle #'.$vehicle->id);
            }

            return self::SUCCESS;
        }

        $this->error('Use --incremental or both --from= and --to=');

        return self::FAILURE;
    }
}
