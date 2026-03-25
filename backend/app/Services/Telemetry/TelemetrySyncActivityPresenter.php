<?php

namespace App\Services\Telemetry;

use App\Jobs\SyncTelemetryScopeFromClickHouseJob;
use App\Jobs\SyncVehicleTelemetryFromClickHouseJob;
use App\Models\DeviceLocation;
use App\Models\PlatformSetting;
use App\Models\TelemetrySyncCursor;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Builds a live “sync log” snapshot for Filament (PostgreSQL + queue state).
 */
final class TelemetrySyncActivityPresenter
{
    public function __construct(
        private readonly ClickHouseLocationCollector $collector,
    ) {}

    /**
     * @return array{
     *     summary: array<string, mixed>,
     *     vehicles: list<array<string, mixed>>,
     *     failed_jobs: list<array<string, mixed>>,
     *     refreshed_at: string
     * }
     */
    public function forView(int $vehicleLimit = 30, int $failedJobLimit = 8): array
    {
        return [
            'summary' => $this->summary(),
            'vehicles' => $this->recentVehicleRows($vehicleLimit),
            'failed_jobs' => $this->recentFailedTelemetryJobs($failedJobLimit),
            'refreshed_at' => now()->timezone(config('app.timezone'))->format('Y-m-d H:i:s T'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(): array
    {
        $queue = config('queue.connections.'.config('queue.default', 'database').'.queue', 'default');
        if (! is_string($queue)) {
            $queue = 'default';
        }

        $pendingJobs = 0;
        if (Schema::hasTable('jobs')) {
            try {
                $pendingJobs = (int) DB::table('jobs')->where('queue', $queue)->count();
            } catch (\Throwable) {
                $pendingJobs = 0;
            }
        }

        $lastTickRaw = PlatformSetting::get(TelemetrySchedulerConfig::KEY_LAST_INCREMENTAL_RUN);

        $cursorKey = (string) config('telemetry.cursor_incremental');
        $cursorAt = TelemetrySyncCursor::query()->where('cursor_key', $cursorKey)->value('last_event_at');

        return [
            'clickhouse_enabled' => $this->collector->isEnabled(),
            'clickhouse_base_url' => (string) config('telemetry.clickhouse.base_url'),
            'automation_incremental_minutes' => TelemetrySchedulerConfig::incrementalIntervalMinutes(),
            'automation_build_sessions_utc' => TelemetrySchedulerConfig::buildSessionsAt(),
            'automation_aggregate_daily_utc' => TelemetrySchedulerConfig::aggregateDailyAt(),
            'last_scheduler_tick' => $this->formatDbDatetime($lastTickRaw),
            'vehicles_total' => Vehicle::query()->count(),
            'vehicles_scheduled_pull' => Vehicle::query()->where('telemetry_pull_enabled', true)->count(),
            'vehicles_with_error' => Vehicle::query()->whereNotNull('telemetry_last_error')->count(),
            'latest_vehicle_success' => $this->formatDbDatetime(Vehicle::query()->max('telemetry_last_success_at')),
            'latest_device_location' => $this->formatDbDatetime(DeviceLocation::query()->max('event_at')),
            'global_incremental_cursor' => $this->formatDbDatetime($cursorAt),
            'pending_jobs_count' => $pendingJobs,
            'pending_jobs_queue' => $queue,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentVehicleRows(int $limit): array
    {
        return Vehicle::query()
            ->orderByRaw('CASE WHEN telemetry_last_success_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('telemetry_last_success_at')
            ->limit($limit)
            ->get([
                'id', 'brand', 'model', 'imei', 'telemetry_pull_enabled',
                'telemetry_last_success_at', 'telemetry_last_incremental_at',
                'telemetry_last_historical_at', 'telemetry_last_error',
            ])
            ->map(fn (Vehicle $v) => [
                'id' => $v->id,
                'label' => trim("{$v->brand} {$v->model}"),
                'imei' => $v->imei,
                'scheduled_pull' => (bool) $v->telemetry_pull_enabled,
                'last_success' => $this->formatDbDatetime($v->telemetry_last_success_at),
                'last_incremental' => $this->formatDbDatetime($v->telemetry_last_incremental_at),
                'last_historical' => $this->formatDbDatetime($v->telemetry_last_historical_at),
                'error' => $v->telemetry_last_error
                    ? Str::limit((string) $v->telemetry_last_error, 140)
                    : null,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentFailedTelemetryJobs(int $limit): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return [];
        }

        $markers = [
            class_basename(SyncVehicleTelemetryFromClickHouseJob::class),
            class_basename(SyncTelemetryScopeFromClickHouseJob::class),
        ];

        $candidates = DB::table('failed_jobs')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'payload', 'exception', 'failed_at']);

        $out = [];
        foreach ($candidates as $row) {
            if (count($out) >= $limit) {
                break;
            }
            $payload = (string) $row->payload;
            $match = false;
            foreach ($markers as $m) {
                if (str_contains($payload, $m)) {
                    $match = true;
                    break;
                }
            }
            if (! $match) {
                continue;
            }

            $decoded = json_decode($payload, true);
            $displayName = is_array($decoded) ? ($decoded['displayName'] ?? null) : null;

            $out[] = [
                'id' => (int) $row->id,
                'failed_at' => $this->formatDbDatetime($row->failed_at),
                'job_label' => is_string($displayName) ? $displayName : $markers[0],
                'error' => Str::limit($this->firstExceptionLine((string) $row->exception), 240),
            ];
        }

        return $out;
    }

    private function firstExceptionLine(string $exception): string
    {
        $exception = trim($exception);
        $lines = preg_split('/\R/', $exception);

        return trim((string) ($lines[0] ?? $exception));
    }

    private function formatDbDatetime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)
                ->timezone(config('app.timezone'))
                ->format('d.m.Y H:i:s T');
        } catch (\Throwable) {
            return null;
        }
    }
}
