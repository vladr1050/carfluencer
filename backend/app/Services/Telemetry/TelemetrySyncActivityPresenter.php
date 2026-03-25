<?php

namespace App\Services\Telemetry;

use App\Jobs\SyncTelemetryScopeFromClickHouseJob;
use App\Jobs\SyncVehicleTelemetryFromClickHouseJob;
use App\Models\DeviceLocation;
use App\Models\PlatformSetting;
use App\Models\TelemetrySyncCursor;
use App\Models\TelemetrySyncEvent;
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
     *     timeline: list<array<string, mixed>>,
     *     refreshed_at: string
     * }
     */
    public function forView(int $vehicleLimit = 30, int $failedJobLimit = 8): array
    {
        return [
            'summary' => $this->summary(),
            'vehicles' => $this->recentVehicleRows($vehicleLimit),
            'failed_jobs' => $this->recentFailedTelemetryJobs($failedJobLimit),
            'timeline' => $this->buildTimeline(24, 150),
            'refreshed_at' => now()->timezone(config('app.timezone'))->format('Y-m-d H:i:s T'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildTimeline(int $hoursBack, int $maxItems): array
    {
        $since = now('UTC')->subHours($hoursBack);
        $items = [];

        if (Schema::hasTable('telemetry_sync_events')) {
            $events = TelemetrySyncEvent::query()
                ->where('occurred_at', '>=', $since)
                ->orderByDesc('occurred_at')
                ->limit(250)
                ->get();

            foreach ($events as $event) {
                $items[] = $this->timelineRowFromEvent($event);
            }
        }

        foreach ($this->failedTelemetryJobsSince($since, 80) as $row) {
            $items[] = $this->timelineRowFromFailedJob($row);
        }

        usort($items, function (array $a, array $b): int {
            return ($b['sort_at'] ?? '') <=> ($a['sort_at'] ?? '');
        });

        return array_slice(array_values($items), 0, $maxItems);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function failedTelemetryJobsSince(Carbon $sinceUtc, int $limit): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return [];
        }

        $markers = [
            class_basename(SyncVehicleTelemetryFromClickHouseJob::class),
            class_basename(SyncTelemetryScopeFromClickHouseJob::class),
        ];

        $candidates = DB::table('failed_jobs')
            ->where('failed_at', '>=', $sinceUtc)
            ->orderByDesc('failed_at')
            ->limit(200)
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

            $out[] = [
                'id' => (int) $row->id,
                'payload' => $payload,
                'exception' => (string) $row->exception,
                'failed_at' => $row->failed_at,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function timelineRowFromEvent(TelemetrySyncEvent $event): array
    {
        $payload = $event->payload ?? [];
        $lines = $this->timelineDetailLinesFromPayload($payload);

        return [
            'key' => 'evt-'.$event->id,
            'sort_at' => $event->occurred_at->copy()->utc()->toIso8601String(),
            'at_display' => $event->occurred_at->copy()->timezone('UTC')->format('Y-m-d H:i:s').' UTC',
            'badge' => $this->timelineBadgeForSource($event->source),
            'severity' => $this->timelineSeverity($event->status),
            'title' => $event->summary ?? $event->action,
            'lines' => $lines,
            'error' => $event->error_message ? Str::limit((string) $event->error_message, 400) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function timelineDetailLinesFromPayload(array $payload): array
    {
        $lines = [];

        if (isset($payload['imeis']) && is_array($payload['imeis'])) {
            $imeis = $payload['imeis'];
            $preview = array_slice($imeis, 0, 12);
            $suffix = count($imeis) > 12 ? ' (+'.(count($imeis) - 12).' '.__('more').')' : '';
            $lines[] = __('IMEIs:').' '.implode(', ', $preview).$suffix;
        }

        if (isset($payload['vehicle_id'])) {
            $lines[] = __('Vehicle ID: :id', ['id' => $payload['vehicle_id']]);
        }

        if (isset($payload['mode'])) {
            $lines[] = __('Mode: :m', ['m' => $payload['mode']]);
        }

        if (isset($payload['scope'])) {
            $lines[] = __('Scope: :s', ['s' => $payload['scope']]);
        }

        if (isset($payload['campaign_id']) && $payload['campaign_id'] !== null) {
            $lines[] = __('Campaign ID: :id', ['id' => $payload['campaign_id']]);
        }

        if (isset($payload['date']) && is_string($payload['date'])) {
            $lines[] = __('Date (UTC): :d', ['d' => $payload['date']]);
        }

        if (isset($payload['date_from'], $payload['date_to'])) {
            $lines[] = __('Window (UTC): :a → :b', ['a' => $payload['date_from'], 'b' => $payload['date_to']]);
        }

        if (isset($payload['rows'])) {
            $lines[] = __('Rows: :n', ['n' => $payload['rows']]);
        }

        if (isset($payload['interval_minutes'])) {
            $lines[] = __('Configured interval (minutes): :n', ['n' => $payload['interval_minutes']]);
        }

        if (isset($payload['failures']) && is_array($payload['failures']) && $payload['failures'] !== []) {
            foreach (array_slice($payload['failures'], 0, 6) as $f) {
                if (is_array($f) && isset($f['imei'])) {
                    $lines[] = __('IMEI :imei — :msg', [
                        'imei' => $f['imei'],
                        'msg' => Str::limit((string) ($f['message'] ?? ''), 120),
                    ]);
                }
            }
            if (count($payload['failures']) > 6) {
                $lines[] = __('…and :n more IMEI error(s).', ['n' => count($payload['failures']) - 6]);
            }
        }

        return $lines;
    }

    private function timelineBadgeForSource(string $source): string
    {
        return match ($source) {
            TelemetrySyncEvent::SOURCE_SCHEDULER => __('Scheduler'),
            TelemetrySyncEvent::SOURCE_ADMIN_QUEUE => __('Admin / queue'),
            default => $source,
        };
    }

    private function timelineSeverity(string $status): string
    {
        return match ($status) {
            TelemetrySyncEvent::STATUS_FAILED => 'error',
            TelemetrySyncEvent::STATUS_PARTIAL => 'warning',
            TelemetrySyncEvent::STATUS_SKIPPED, TelemetrySyncEvent::STATUS_INFO => 'info',
            default => 'success',
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function timelineRowFromFailedJob(array $row): array
    {
        $payload = (string) $row['payload'];
        $decoded = json_decode($payload, true);
        $displayName = is_array($decoded) ? ($decoded['displayName'] ?? null) : null;
        $label = is_string($displayName) ? $displayName : __('Telemetry queue job');

        try {
            $at = Carbon::parse($row['failed_at'], 'UTC');
        } catch (\Throwable) {
            $at = now('UTC');
        }

        return [
            'key' => 'fail-'.$row['id'],
            'sort_at' => $at->toIso8601String(),
            'at_display' => $at->timezone('UTC')->format('Y-m-d H:i:s').' UTC',
            'badge' => __('Failed job'),
            'severity' => 'error',
            'title' => $label,
            'lines' => [__('See failed_jobs id :id.', ['id' => $row['id']])],
            'error' => Str::limit($this->firstExceptionLine((string) $row['exception']), 400),
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
