<?php

namespace App\Jobs;

use App\Models\TelemetrySyncEvent;
use App\Models\Vehicle;
use App\Services\Telemetry\ClickHouseLocationCollector;
use App\Services\Telemetry\HeatmapRollupAfterTelemetryImport;
use App\Services\Telemetry\TelemetrySyncEventRecorder;
use App\Services\Telemetry\TelemetryVehicleSyncState;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/** @see SyncTelemetryScopeFromClickHouseJob (same worker timeout / queue retry_after requirements) */
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
        $mem = config('telemetry.clickhouse.queue_memory_limit');
        if (is_string($mem) && $mem !== '' && $mem !== '0') {
            @ini_set('memory_limit', $mem);
        }

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
                TelemetrySyncEventRecorder::record(
                    TelemetrySyncEvent::SOURCE_ADMIN_QUEUE,
                    TelemetrySyncEvent::ACTION_MANUAL_VEHICLE_SYNC,
                    TelemetrySyncEvent::STATUS_SUCCESS,
                    'Admin queue: incremental sync for vehicle '.$this->vehicleId.' ('.$n.' rows).',
                    [
                        'vehicle_id' => $this->vehicleId,
                        'imei' => $imei,
                        'mode' => 'incremental',
                        'rows' => $n,
                    ],
                );

                HeatmapRollupAfterTelemetryImport::dispatchRollingAfterIncremental($n);

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
                TelemetrySyncEventRecorder::record(
                    TelemetrySyncEvent::SOURCE_ADMIN_QUEUE,
                    TelemetrySyncEvent::ACTION_MANUAL_VEHICLE_SYNC,
                    TelemetrySyncEvent::STATUS_SUCCESS,
                    'Admin queue: historical sync for vehicle '.$this->vehicleId.' ('.$n.' rows).',
                    [
                        'vehicle_id' => $this->vehicleId,
                        'imei' => $imei,
                        'mode' => 'historical',
                        'date_from' => $this->dateFrom,
                        'date_to' => $this->dateTo,
                        'rows' => $n,
                    ],
                );

                HeatmapRollupAfterTelemetryImport::dispatchForHistoricalWindow($this->dateFrom, $this->dateTo);
            }
        } catch (Throwable $e) {
            $syncState->recordFailure($vehicle, $e);
            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception === null) {
            return;
        }

        Log::error('SyncVehicleTelemetryFromClickHouseJob failed permanently', [
            'vehicle_id' => $this->vehicleId,
            'mode' => $this->mode,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'previous' => $exception->getPrevious()?->getMessage(),
        ]);
    }
}
