@php
    /** @var array $breakdown */
    $breakdown = $breakdown ?? [];
@endphp

<div class="fi-section mt-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
        Top 10 districts (Geo zones) by estimated impressions
    </h3>

    @if (! ($breakdown['available'] ?? false))
        <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
            {{ $breakdown['reason'] ?? 'Breakdown is not available for this snapshot.' }}
        </p>
    @else
        @if (! empty($breakdown['note']))
            <p class="mt-2 text-sm text-amber-700 dark:text-amber-400">
                {{ $breakdown['note'] }}
            </p>
        @endif

        @php
            $rows = $breakdown['top_zones'] ?? [];
            $maxImp = 0;
            foreach ($rows as $r) {
                $maxImp = max($maxImp, (int) ($r['impressions'] ?? 0));
            }
        @endphp

        @if ($rows === [])
            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                No attributed impressions to active Geo zones for this period (check zones or exposure data).
            </p>
        @else
            @php
                $zoneGrid = 'grid items-start gap-x-6 gap-y-2 sm:gap-x-10 [grid-template-columns:minmax(0,1.35fr)_minmax(0,1.15fr)_auto_auto_minmax(0,1fr)]';
            @endphp
            <div class="mt-4 min-w-0 overflow-x-auto rounded-lg ring-1 ring-gray-200 dark:ring-white/10">
                <div class="min-w-[52rem] text-sm text-gray-900 dark:text-gray-100">
                    {{-- CSS grid so Filament/parent styles cannot collapse table cells into one line --}}
                    <div
                        role="row"
                        class="{{ $zoneGrid }} border-b border-gray-200 bg-gray-50/90 px-4 py-3 dark:border-white/10 dark:bg-white/5"
                    >
                        <div role="columnheader" class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            District
                        </div>
                        <div role="columnheader" class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Code
                        </div>
                        <div
                            role="columnheader"
                            class="border-l-2 border-dashed border-gray-300 pl-6 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-white/20 dark:text-gray-400"
                        >
                            Impressions
                        </div>
                        <div
                            role="columnheader"
                            class="border-l border-gray-200 pl-5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-white/15 dark:text-gray-400"
                        >
                            Share
                        </div>
                        <div role="columnheader" class="pl-4 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Visual
                        </div>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($rows as $row)
                            @php
                                $imp = (int) ($row['impressions'] ?? 0);
                                $pct = (float) ($row['share_pct'] ?? 0);
                                $barW = $maxImp > 0 ? round(100 * $imp / $maxImp) : 0;
                            @endphp
                            <div role="row" class="{{ $zoneGrid }} px-4 py-3.5">
                                <div class="min-w-0 pr-2">
                                    <span class="line-clamp-2 break-words text-gray-900 dark:text-gray-100">
                                        {{ $row['name'] ?? '—' }}
                                    </span>
                                </div>
                                <div class="min-w-0 pr-2">
                                    <span class="block font-mono text-xs leading-snug tracking-tight text-gray-700 dark:text-gray-300">
                                        {{ $row['code'] ?? '—' }}
                                    </span>
                                </div>
                                <div
                                    class="shrink-0 border-l-2 border-dashed border-gray-300 pl-6 text-right text-base font-semibold tabular-nums text-gray-950 dark:border-white/25 dark:text-white"
                                >
                                    {{ number_format($imp) }}
                                </div>
                                <div
                                    class="shrink-0 border-l border-gray-200 pl-5 text-right tabular-nums text-gray-800 dark:border-white/15 dark:text-gray-100"
                                >
                                    {{ number_format($pct, 2) }}%
                                </div>
                                <div class="min-w-[6rem] pl-4">
                                    <div class="h-2 w-full overflow-hidden rounded bg-gray-100 dark:bg-white/10">
                                        <div
                                            class="h-2 rounded bg-amber-500 dark:bg-amber-400"
                                            style="width: {{ $barW }}%"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="mt-6 flex flex-wrap items-end justify-between gap-4 border-t border-gray-200 pt-4 dark:border-white/10">
            <div>
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Total (from zone breakdown)</div>
                <div class="text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">
                    {{ number_format((int) ($breakdown['total_impressions'] ?? 0)) }}
                </div>
            </div>
            <div class="text-right text-sm text-gray-600 dark:text-gray-400">
                <div>
                    Unattributed (outside zones):
                    <span class="font-medium tabular-nums text-gray-900 dark:text-gray-100">
                        {{ number_format((int) ($breakdown['unattributed_impressions'] ?? 0)) }}
                    </span>
                    <span class="tabular-nums">({{ number_format((float) ($breakdown['unattributed_share_pct'] ?? 0), 2) }}%)</span>
                </div>
            </div>
        </div>
    @endif
</div>
