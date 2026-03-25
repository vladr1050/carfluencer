<?php

namespace App\Services\Telemetry;

use App\Models\TelemetrySyncEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Persists admin-visible telemetry timeline rows (scheduler + queued manual sync).
 */
final class TelemetrySyncEventRecorder
{
    public static function record(
        string $source,
        string $action,
        string $status,
        ?string $summary = null,
        ?array $payload = null,
        ?string $errorMessage = null,
        ?Carbon $occurredAt = null,
    ): void {
        if (! Schema::hasTable('telemetry_sync_events')) {
            return;
        }

        try {
            TelemetrySyncEvent::query()->create([
                'occurred_at' => $occurredAt ?? now('UTC'),
                'source' => $source,
                'action' => $action,
                'status' => $status,
                'summary' => $summary,
                'error_message' => $errorMessage,
                'payload' => $payload,
            ]);
        } catch (\Throwable) {
            //
        }
    }
}
