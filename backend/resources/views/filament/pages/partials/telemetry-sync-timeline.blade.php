@php
    /** @var list<array<string, mixed>> $timeline */
    /** @var array<string, mixed> $summary */
    /** @var string $refreshed_at */
    $borders = [
        'success' => 'border-green-500 dark:border-green-400',
        'warning' => 'border-amber-500 dark:border-amber-400',
        'error' => 'border-red-500 dark:border-red-400',
        'info' => 'border-sky-500 dark:border-sky-400',
    ];
@endphp

<div wire:poll.15s class="space-y-4">
    <p class="text-xs text-gray-500 dark:text-gray-400">
        {{ __('Last updated: :t', ['t' => $refreshed_at]) }}
    </p>

    @unless ($summary['clickhouse_enabled'])
        <div class="rounded-lg border-2 border-amber-500/80 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-400/60 dark:bg-amber-500/10 dark:text-amber-50">
            <p class="font-semibold">{{ __('ClickHouse import is disabled — sync jobs do nothing') }}</p>
            <p class="mt-1 text-xs">{{ __('Enable TELEMETRY_CLICKHOUSE_ENABLED in .env and restart the queue worker.') }}</p>
        </div>
    @endunless

    @if (count($timeline) === 0)
        <p class="rounded-lg border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-600 dark:border-white/20 dark:bg-gray-900 dark:text-gray-400">
            {{ __('No telemetry events in the last 24 hours yet. After the next scheduler tick, manual sync, or failed queue job, entries will appear here. Run `php artisan migrate` if this panel was just deployed.') }}
        </p>
    @else
        <ol class="space-y-3">
            @foreach ($timeline as $row)
                @php
                    $sev = $row['severity'] ?? 'info';
                    $border = $borders[$sev] ?? $borders['info'];
                @endphp
                <li class="rounded-lg border-s-4 {{ $border }} border-y border-e border-gray-200 bg-white py-3 pe-4 ps-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <span class="font-mono text-xs text-gray-600 dark:text-gray-400">{{ $row['at_display'] ?? '—' }}</span>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300">{{ $row['badge'] ?? '—' }}</span>
                    </div>
                    <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $row['title'] ?? '—' }}</p>
                    @if (! empty($row['lines']))
                        <ul class="mt-2 list-disc space-y-0.5 ps-4 text-xs text-gray-700 dark:text-gray-300">
                            @foreach ($row['lines'] as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if (! empty($row['error']))
                        <pre class="mt-2 max-h-32 overflow-auto whitespace-pre-wrap break-words rounded bg-red-50 p-2 font-mono text-xs text-red-900 dark:bg-red-950/50 dark:text-red-100">{{ $row['error'] }}</pre>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</div>
