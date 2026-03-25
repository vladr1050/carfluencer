@php
    /** @var array $summary */
    /** @var array $vehicles */
    /** @var array $failed_jobs */
    /** @var string $refreshed_at */
@endphp

<div wire:poll.15s class="space-y-6">
    <p class="text-xs text-gray-500 dark:text-gray-400">
        {{ __('Last updated: :t', ['t' => $refreshed_at]) }}
    </p>

    @unless ($summary['clickhouse_enabled'])
        <div class="rounded-lg border-2 border-amber-500/80 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-400/60 dark:bg-amber-500/10 dark:text-amber-50">
            <p class="font-semibold">{{ __('ClickHouse import is disabled — sync jobs do nothing') }}</p>
            <p class="mt-2 leading-relaxed">
                {{ __('Laravel reads TELEMETRY_CLICKHOUSE_ENABLED from .env (default false). While it is false, queued jobs return immediately: no GPS rows are loaded, timestamps in this log stay empty, and the queue worker may show no “Processing …” lines.') }}
            </p>
            <ol class="mt-3 list-decimal space-y-1 ps-5">
                <li><code class="rounded bg-black/10 px-1 dark:bg-white/10">TELEMETRY_CLICKHOUSE_ENABLED=true</code></li>
                <li><code class="rounded bg-black/10 px-1 dark:bg-white/10">TELEMETRY_CLICKHOUSE_URL=…</code> {{ __('(current config base URL: :u)', ['u' => $summary['clickhouse_base_url'] ?: '—']) }}</li>
                <li>{{ __('Run') }} <code class="rounded bg-black/10 px-1 dark:bg-white/10">php artisan config:clear</code> {{ __('or') }} <code class="rounded bg-black/10 px-1 dark:bg-white/10">config:cache</code>, {{ __('then restart the queue worker.') }}</li>
            </ol>
            <p class="mt-2 text-xs opacity-90">{{ __('See backend/.env.production.example and docs/ARCHITECTURE/05_telemetry_pipeline.md') }}</p>
        </div>
    @endunless

    <div class="rounded-lg border border-gray-200 bg-gray-50/80 p-4 text-sm dark:border-white/10 dark:bg-white/5">
        <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Effective automation (from database)') }}</div>
        <dl class="mt-2 grid gap-2 sm:grid-cols-3">
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Incremental pull interval (minutes)') }}</dt>
                <dd class="mt-0.5 font-mono font-semibold text-gray-950 dark:text-white">{{ (int) ($summary['automation_incremental_minutes'] ?? 0) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Daily stop/sessions (UTC)') }}</dt>
                <dd class="mt-0.5 font-mono font-semibold text-gray-950 dark:text-white">{{ $summary['automation_build_sessions_utc'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500 dark:text-gray-400">{{ __('Daily aggregates (UTC)') }}</dt>
                <dd class="mt-0.5 font-mono font-semibold text-gray-950 dark:text-white">{{ $summary['automation_aggregate_daily_utc'] ?? '—' }}</dd>
            </div>
        </dl>
        <p class="mt-3 text-xs leading-relaxed text-gray-600 dark:text-gray-400">
            {{ __('These values are what the scheduler uses after you click “Save automation settings”. If the tick runs hourly (`0 * * * *` cron), incremental pull cannot happen more than once per hour regardless of this number. Daily UTC times mean “after this moment”; jobs run on the first scheduler pass after that (often at :00).') }}
        </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('ClickHouse pull') }}</div>
            <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                @if ($summary['clickhouse_enabled'])
                    <span class="text-green-600 dark:text-green-400">{{ __('Enabled') }}</span>
                @else
                    <span class="text-red-600 dark:text-red-400">{{ __('Disabled') }}</span>
                @endif
            </div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Latest vehicle sync (success)') }}</div>
            <div class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $summary['latest_vehicle_success'] ?? '—' }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Last scheduler incremental tick') }}</div>
            <div class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $summary['last_scheduler_tick'] ?? '—' }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Jobs waiting in queue “:q”', ['q' => $summary['pending_jobs_queue']]) }}</div>
            <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ number_format($summary['pending_jobs_count']) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Vehicles (scheduled pull / errors)') }}</div>
            <div class="mt-1 text-sm text-gray-950 dark:text-white">
                {{ __('Total: :n', ['n' => number_format($summary['vehicles_total'])]) }}
                · {{ __('Scheduled: :n', ['n' => number_format($summary['vehicles_scheduled_pull'])]) }}
                · <span class="@if(($summary['vehicles_with_error'] ?? 0) > 0) text-red-600 dark:text-red-400 @endif">{{ __('Errors: :n', ['n' => number_format($summary['vehicles_with_error'])]) }}</span>
            </div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Latest stored GPS point (PostgreSQL)') }}</div>
            <div class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $summary['latest_device_location'] ?? '—' }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('Global incremental cursor (CH watermark)') }}</div>
            <div class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $summary['global_incremental_cursor'] ?? '—' }}</div>
        </div>
    </div>

    <div>
        <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">{{ __('Recent vehicle sync (PostgreSQL)') }}</h3>
        <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">{{ __('Per-vehicle timestamps written by ClickHouse → PostgreSQL jobs. “Sch.” = scheduled pull in Fleet.') }}</p>
        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table class="w-full min-w-[720px] divide-y divide-gray-200 text-start text-sm dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">{{ __('Vehicle') }}</th>
                        <th class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">{{ __('IMEI') }}</th>
                        <th class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">{{ __('Sch.') }}</th>
                        <th class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">{{ __('Last success') }}</th>
                        <th class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">{{ __('Incremental') }}</th>
                        <th class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">{{ __('Historical') }}</th>
                        <th class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">{{ __('Last error') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white dark:divide-white/10 dark:bg-gray-900">
                    @forelse ($vehicles as $row)
                        <tr class="align-top">
                            <td class="px-3 py-2 text-gray-950 dark:text-white">
                                <a href="{{ \App\Filament\Resources\Vehicles\VehicleResource::getUrl('edit', ['record' => $row['id']]) }}" class="text-primary-600 hover:underline dark:text-primary-400">
                                    {{ $row['label'] ?: '—' }}
                                </a>
                            </td>
                            <td class="px-3 py-2 font-mono text-xs text-gray-600 dark:text-gray-400">{{ $row['imei'] }}</td>
                            <td class="px-3 py-2">{{ $row['scheduled_pull'] ? __('Yes') : __('No') }}</td>
                            <td class="px-3 py-2 text-gray-800 dark:text-gray-200">{{ $row['last_success'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $row['last_incremental'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $row['last_historical'] ?? '—' }}</td>
                            <td class="max-w-xs px-3 py-2 text-xs text-red-600 dark:text-red-400">{{ $row['error'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">{{ __('No vehicles yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">{{ __('Failed telemetry jobs (queue log)') }}</h3>
        <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">{{ __('Latest failures whose payload matches ClickHouse sync jobs. Inspect with php artisan queue:failed, then retry or forget after fixing the cause.') }}</p>
        @if (count($failed_jobs) === 0)
            <p class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600 dark:border-white/10 dark:bg-gray-900 dark:text-gray-400">{{ __('No matching failed jobs in the table (or none yet).') }}</p>
        @else
            <ul class="space-y-3">
                @foreach ($failed_jobs as $job)
                    <li class="rounded-lg border border-red-200 bg-red-50/60 p-3 text-sm dark:border-red-500/30 dark:bg-red-950/40">
                        <div class="font-medium text-gray-950 dark:text-white">{{ $job['job_label'] }}</div>
                        <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ __('Failed at: :t', ['t' => $job['failed_at'] ?? '—']) }} · id {{ $job['id'] }}</div>
                        <pre class="mt-2 max-h-24 overflow-auto whitespace-pre-wrap break-words font-mono text-xs text-red-800 dark:text-red-200">{{ $job['error'] }}</pre>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
