<?php

namespace App\Jobs;

use App\Services\Telemetry\ClickHouseLocationCollector;
use App\Services\Telemetry\TelemetrySyncImeiResolver;
use App\Services\Telemetry\TelemetryVehicleSyncState;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued telemetry sync for platform-scoped IMEIs: all vehicles, one campaign, or explicit vehicle IDs.
 * (No “whole ClickHouse table” scope — use CLI/scheduler if you need that.)
 */
class SyncTelemetryScopeFromClickHouseJob implements ShouldQueue
{
    use Queueable;

    /** Historical sync may page through ClickHouse for a long window; keep above worst-case runtime. */
    public int $timeout = 7200;

    /**
     * @param  'incremental'|'historical'  $mode
     * @param  'all_vehicles'|'campaign'|'vehicles'  $scope
     * @param  list<int>  $vehicleIds
     */
    public function __construct(
        public string $mode,
        public string $scope,
        public ?int $campaignId = null,
        public array $vehicleIds = [],
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
    ) {}

    public function handle(
        ClickHouseLocationCollector $collector,
        TelemetrySyncImeiResolver $resolver,
        TelemetryVehicleSyncState $syncState,
    ): void {
        if (! $collector->isEnabled()) {
            Log::warning('SyncTelemetryScopeFromClickHouseJob skipped: ClickHouse disabled', [
                'mode' => $this->mode,
                'scope' => $this->scope,
            ]);

            return;
        }

        if ($this->scope === 'global') {
            Log::warning('SyncTelemetryScopeFromClickHouseJob: scope "global" is no longer supported; use telemetry:sync-incremental / telemetry:sync-historical or platform scopes.', [
                'mode' => $this->mode,
            ]);

            return;
        }

        $imeis = match ($this->scope) {
            'all_vehicles', 'campaign', 'vehicles' => $resolver->resolve($this->scope, $this->campaignId, $this->vehicleIds),
            default => [],
        };

        if ($imeis === []) {
            Log::info('SyncTelemetryScopeFromClickHouseJob: no IMEIs for scope', [
                'mode' => $this->mode,
                'scope' => $this->scope,
                'campaign_id' => $this->campaignId,
            ]);

            return;
        }

        try {
            if ($this->mode === 'incremental') {
                $pauseUs = ((int) config('telemetry.clickhouse.pause_ms_between_imei', 300)) * 1000;
                $n = 0;
                $lastIdx = count($imeis) - 1;
                foreach ($imeis as $idx => $imei) {
                    try {
                        $n += $collector->syncIncrementalForImei($imei);
                        $syncState->markIncrementalSuccessForImeis([$imei]);
                    } catch (Throwable $e) {
                        $syncState->recordFailureForImeis([$imei], $e);
                        Log::warning('Telemetry incremental sync: IMEI failed (scoped job continues)', [
                            'imei' => $imei,
                            'scope' => $this->scope,
                            'campaign_id' => $this->campaignId,
                            'message' => $e->getMessage(),
                        ]);
                    }
                    if ($pauseUs > 0 && $idx < $lastIdx) {
                        usleep($pauseUs);
                    }
                }
                Log::info('Telemetry incremental sync (scoped)', [
                    'scope' => $this->scope,
                    'campaign_id' => $this->campaignId,
                    'imei_count' => count($imeis),
                    'rows' => $n,
                ]);

                return;
            }

            if ($this->mode === 'historical' && $this->dateFrom !== null && $this->dateTo !== null) {
                $from = Carbon::parse($this->dateFrom, 'UTC')->startOfDay();
                $to = Carbon::parse($this->dateTo, 'UTC')->endOfDay()->addSecond();
                $n = $collector->syncHistoricalForImeis($imeis, $from, $to);
                $collector->advanceIncrementalCursorsAfterHistorical($imeis, $to);
                $syncState->markHistoricalSuccessForImeis($imeis);
                Log::info('Telemetry historical sync (scoped)', [
                    'scope' => $this->scope,
                    'campaign_id' => $this->campaignId,
                    'imei_count' => count($imeis),
                    'from' => $this->dateFrom,
                    'to' => $this->dateTo,
                    'rows' => $n,
                ]);
            }
        } catch (Throwable $e) {
            // Incremental path records per-IMEI above; only historical uses this bulk failure.
            if ($this->mode === 'historical') {
                $syncState->recordFailureForImeis($imeis, $e);
            }
            throw $e;
        }
    }
}
