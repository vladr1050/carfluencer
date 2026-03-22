<?php

namespace App\Jobs;

use App\Models\Vehicle;
use App\Services\Telemetry\ClickHouseLocationCollector;
use App\Services\Telemetry\TelemetryVehicleSyncState;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncVehicleTelemetryFromClickHouseJob implements ShouldQueue
{
    use Queueable;

    /** Historical backfill pages until the window is exhausted. */
    public int $timeout = 7200;

    public function __construct(
        public int $vehicleId,
        public string $mode,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
    ) {}

    public function handle(
        ClickHouseLocationCollector $collector,
        TelemetryVehicleSyncState $syncState,
    ): void {
        $vehicle = Vehicle::query()->find($this->vehicleId);
        if ($vehicle === null) {
            return;
        }

        if (! $collector->isEnabled()) {
            Log::warning('SyncVehicleTelemetryFromClickHouseJob skipped: ClickHouse disabled', [
                'vehicle_id' => $this->vehicleId,
            ]);

            return;
        }

        $imei = $vehicle->imei;

        try {
            if ($this->mode === 'incremental') {
                $n = $collector->syncIncrementalForImei($imei);
                $syncState->markIncrementalSuccess($vehicle);

                Log::info('Telemetry incremental sync for vehicle', ['vehicle_id' => $this->vehicleId, 'imei' => $imei, 'rows' => $n]);

                return;
            }

            if ($this->mode === 'historical' && $this->dateFrom !== null && $this->dateTo !== null) {
                $from = Carbon::parse($this->dateFrom, 'UTC')->startOfDay();
                $to = Carbon::parse($this->dateTo, 'UTC')->endOfDay()->addSecond();
                $n = $collector->syncHistoricalForImei($imei, $from, $to);
                $collector->advanceIncrementalCursorAfterHistorical($imei, $to);
                $syncState->markHistoricalSuccess($vehicle);

                Log::info('Telemetry historical sync for vehicle', [
                    'vehicle_id' => $this->vehicleId,
                    'imei' => $imei,
                    'from' => $this->dateFrom,
                    'to' => $this->dateTo,
                    'rows' => $n,
                ]);
            }
        } catch (Throwable $e) {
            $syncState->recordFailure($vehicle, $e);
            throw $e;
        }
    }
}
