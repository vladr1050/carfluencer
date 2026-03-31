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
            <div class="mt-4 min-w-0 overflow-x-auto rounded-lg ring-1 ring-gray-200 dark:ring-white/10">
                <table
                    class="w-full min-w-[40rem] table-fixed border-collapse text-left text-sm text-gray-900 dark:text-gray-100"
                >
                    <colgroup>
                        <col class="w-[28%]" />
                        <col class="w-[26%]" />
                        <col class="w-[14%]" />
                        <col class="w-[12%]" />
                        <col class="w-[20%]" />
                    </colgroup>
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50/90 dark:border-white/10 dark:bg-white/5">
                            <th
                                scope="col"
                                class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                District
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Code
                            </th>
                            <th
                                scope="col"
                                class="border-l-2 border-dashed border-gray-300 px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-white/20 dark:text-gray-400"
                            >
                                Impressions
                            </th>
                            <th
                                scope="col"
                                class="border-l border-gray-200 px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-white/15 dark:text-gray-400"
                            >
                                Share
                            </th>
                            <th
                                scope="col"
                                class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Visual
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($rows as $row)
                            @php
                                $imp = (int) ($row['impressions'] ?? 0);
                                $pct = (float) ($row['share_pct'] ?? 0);
                                $barW = $maxImp > 0 ? round(100 * $imp / $maxImp) : 0;
                            @endphp
                            <tr class="align-top">
                                <td class="max-w-[14rem] px-4 py-3.5">
                                    <span class="line-clamp-2 break-words text-gray-900 dark:text-gray-100">
                                        {{ $row['name'] ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="font-mono text-xs leading-snug tracking-tight text-gray-700 dark:text-gray-300">
                                        {{ $row['code'] ?? '—' }}
                                    </span>
                                </td>
                                <td
                                    class="border-l-2 border-dashed border-gray-300 px-6 py-3.5 text-right text-base font-semibold tabular-nums text-gray-950 dark:border-white/25 dark:text-white"
                                >
                                    {{ number_format($imp) }}
                                </td>
                                <td
                                    class="border-l border-gray-200 px-5 py-3.5 text-right tabular-nums text-gray-800 dark:border-white/15 dark:text-gray-100"
                                >
                                    {{ number_format($pct, 2) }}%
                                </td>
                                <td class="min-w-[6rem] px-4 py-3.5">
                                    <div class="h-2 w-full overflow-hidden rounded bg-gray-100 dark:bg-white/10">
                                        <div
                                            class="h-2 rounded bg-amber-500 dark:bg-amber-400"
                                            style="width: {{ $barW }}%"
                                        ></div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
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
