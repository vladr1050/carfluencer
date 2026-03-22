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
