<?php

namespace App\Services\Telemetry;

use App\Models\Vehicle;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Persists per-vehicle ClickHouse → PostgreSQL sync status for admin UX.
 */
final class TelemetryVehicleSyncState
{
    public function markIncrementalSuccess(Vehicle $vehicle): void
    {
        $now = now('UTC');
        $vehicle->forceFill([
            'telemetry_last_incremental_at' => $now,
            'telemetry_last_success_at' => $now,
            'telemetry_last_error' => null,
        ])->save();
    }

    public function markHistoricalSuccess(Vehicle $vehicle): void
    {
        $now = now('UTC');
        $vehicle->forceFill([
            'telemetry_last_historical_at' => $now,
            'telemetry_last_success_at' => $now,
            'telemetry_last_error' => null,
        ])->save();
    }

    public function recordFailure(Vehicle $vehicle, Throwable $e): void
    {
        $vehicle->forceFill([
            'telemetry_last_error' => $this->truncateMessage($e->getMessage()),
        ])->save();

        Log::warning('Telemetry sync failed for vehicle', [
            'vehicle_id' => $vehicle->id,
            'imei' => $vehicle->imei,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * @param  list<string>  $imeis  Normalized IMEI strings (digits only)
     */
    public function markIncrementalSuccessForImeis(array $imeis): void
    {
        if ($imeis === []) {
            return;
        }

        $now = now('UTC');
        Vehicle::query()
            ->whereIn('imei', $imeis)
            ->update([
                'telemetry_last_incremental_at' => $now,
                'telemetry_last_success_at' => $now,
                'telemetry_last_error' => null,
            ]);
    }

    /**
     * @param  list<string>  $imeis
     */
    public function markHistoricalSuccessForImeis(array $imeis): void
    {
        if ($imeis === []) {
            return;
        }

        $now = now('UTC');
        Vehicle::query()
            ->whereIn('imei', $imeis)
            ->update([
                'telemetry_last_historical_at' => $now,
                'telemetry_last_success_at' => $now,
                'telemetry_last_error' => null,
            ]);
    }

    /**
     * @param  list<string>  $imeis
     */
    public function recordFailureForImeis(array $imeis, Throwable $e): void
    {
        if ($imeis === []) {
            return;
        }

        $msg = $this->truncateMessage($e->getMessage());
        Vehicle::query()
            ->whereIn('imei', $imeis)
            ->update(['telemetry_last_error' => $msg]);

        Log::warning('Telemetry scoped sync failed', [
            'imei_count' => count($imeis),
            'error' => $e->getMessage(),
        ]);
    }

    private function truncateMessage(string $message): string
    {
        $message = trim($message);

        return mb_strlen($message) > 2000 ? mb_substr($message, 0, 1997).'...' : $message;
    }
}
