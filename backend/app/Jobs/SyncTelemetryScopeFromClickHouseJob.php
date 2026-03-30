<?php

namespace App\Jobs;

use App\Models\TelemetrySyncEvent;
use App\Services\Telemetry\ClickHouseLocationCollector;
use App\Services\Telemetry\TelemetrySyncEventRecorder;
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
 *
 * Ops: worker needs `queue:work … --timeout=7200` (see deploy/supervisor-laravel.conf.example). With the
 * database/redis drivers, `retry_after` must be greater than this job’s runtime (see config/queue.php).
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
            TelemetrySyncEventRecorder::record(
                TelemetrySyncEvent::SOURCE_ADMIN_QUEUE,
                TelemetrySyncEvent::ACTION_MANUAL_SCOPE_SYNC,
                TelemetrySyncEvent::STATUS_INFO,
                'Admin queue: scoped sync — no IMEIs resolved ('.$this->scope.').',
                [
                    'mode' => $this->mode,
                    'scope' => $this->scope,
                    'campaign_id' => $this->campaignId,
                    'vehicle_ids' => $this->vehicleIds,
                ],
            );

            return;
        }

        try {
            if ($this->mode === 'incremental') {
                $pauseUs = ((int) config('telemetry.clickhouse.pause_ms_between_imei', 300)) * 1000;
                $n = 0;
                $lastIdx = count($imeis) - 1;
                $failures = [];
                foreach ($imeis as $idx => $imei) {
                    try {
                        $n += $collector->syncIncrementalForImei($imei);
                        $syncState->markIncrementalSuccessForImeis([$imei]);
                    } catch (Throwable $e) {
                        $syncState->recordFailureForImeis([$imei], $e);
                        $failures[] = ['imei' => $imei, 'message' => $e->getMessage()];
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
                $st = $failures === []
                    ? TelemetrySyncEvent::STATUS_SUCCESS
                    : (count($failures) === count($imeis) ? TelemetrySyncEvent::STATUS_FAILED : TelemetrySyncEvent::STATUS_PARTIAL);
                TelemetrySyncEventRecorder::record(
                    TelemetrySyncEvent::SOURCE_ADMIN_QUEUE,
                    TelemetrySyncEvent::ACTION_MANUAL_SCOPE_SYNC,
                    $st,
                    'Admin queue: scoped incremental ('.$this->scope.', '.$n.' rows, '.count($imeis).' IMEI(s)).',
                    [
                        'mode' => 'incremental',
                        'scope' => $this->scope,
                        'campaign_id' => $this->campaignId,
                        'imeis' => $imeis,
                        'rows' => $n,
                        'failures' => $failures,
                    ],
                    $failures !== [] ? implode('; ', array_column($failures, 'message')) : null,
                );

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
                TelemetrySyncEventRecorder::record(
                    TelemetrySyncEvent::SOURCE_ADMIN_QUEUE,
                    TelemetrySyncEvent::ACTION_MANUAL_SCOPE_SYNC,
                    TelemetrySyncEvent::STATUS_SUCCESS,
                    'Admin queue: scoped historical ('.$this->scope.', '.$n.' rows).',
                    [
                        'mode' => 'historical',
                        'scope' => $this->scope,
                        'campaign_id' => $this->campaignId,
                        'imeis' => $imeis,
                        'date_from' => $this->dateFrom,
                        'date_to' => $this->dateTo,
                        'rows' => $n,
                    ],
                );
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
