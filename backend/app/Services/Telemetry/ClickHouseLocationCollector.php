<?php

namespace App\Services\Telemetry;

use App\Models\DeviceLocation;
use App\Models\TelemetrySyncCursor;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Pulls rows from ClickHouse into PostgreSQL device_locations (incremental + historical).
 */
class ClickHouseLocationCollector
{
    public function __construct(
        private readonly ClickHouseHttpClient $client,
        private readonly DeviceLocationBulkImporter $importer,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('telemetry.clickhouse.enabled');
    }

    /**
     * Global incremental sync (all devices in ClickHouse table).
     *
     * @return int Imported row count (mapped)
     */
    public function syncIncremental(?int $limit = null): int
    {
        if (! $this->isEnabled()) {
            Log::info('ClickHouse collector skipped (TELEMETRY_CLICKHOUSE_ENABLED=false).');

            return 0;
        }

        $limit ??= (int) config('telemetry.clickhouse.global_incremental_rows', 25_000);
        $limit = max(500, min(2_000_000, $limit));

        $key = (string) config('telemetry.cursor_incremental');
        $cursor = TelemetrySyncCursor::query()->firstOrCreate(
            ['cursor_key' => $key],
            ['last_event_at' => null]
        );

        $where = $this->buildTimeWhereAfter($cursor->last_event_at);
        $sql = $this->buildSelectSql($where, $limit);
        $batchSize = (int) config('telemetry.clickhouse.json_each_row_stream_batch', 2500);
        $imported = 0;
        $max = null;
        $this->client->consumeJsonEachRowBatches($sql, $batchSize, function (array $batch) use (&$imported, &$max): void {
            $imported += $this->importer->import($batch);
            foreach ($batch as $row) {
                $max = $this->mergeMaxEventAtFromRow($max, $row);
            }
        });
        if ($max !== null) {
            if ($cursor->last_event_at === null || $max->gt($cursor->last_event_at)) {
                $cursor->last_event_at = $max;
                $cursor->save();
            }
        }

        return $imported;
    }

    /**
     * Incremental sync for a single IMEI (separate cursor).
     *
     * @return int Imported row count
     */
    public function syncIncrementalForImei(string $imei, ?int $limit = null): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $limit ??= (int) config('telemetry.clickhouse.incremental_rows_per_imei', 15_000);
        $limit = max(500, min(500_000, $limit));

        $imei = $this->sanitizeImei($imei);
        $key = 'clickhouse:imei:'.$imei.':incremental';
        $cursor = TelemetrySyncCursor::query()->firstOrCreate(
            ['cursor_key' => $key],
            ['last_event_at' => null]
        );

        $where = $this->buildTimeWhereAfter($cursor->last_event_at);
        $where .= ' AND '.$this->deviceColumnSql()." = '{$imei}'";
        $sql = $this->buildSelectSql($where, $limit);
        $batchSize = (int) config('telemetry.clickhouse.json_each_row_stream_batch', 2500);
        $imported = 0;
        $max = null;
        $this->client->consumeJsonEachRowBatches($sql, $batchSize, function (array $batch) use (&$imported, &$max): void {
            $imported += $this->importer->import($batch);
            foreach ($batch as $row) {
                $max = $this->mergeMaxEventAtFromRow($max, $row);
            }
        });
        if ($max !== null) {
            if ($cursor->last_event_at === null || $max->gt($cursor->last_event_at)) {
                $cursor->last_event_at = $max;
                $cursor->save();
            }
        }

        return $imported;
    }

    /**
     * Historical backfill for a window (does not move global incremental cursor).
     *
     * @return int Imported row count
     */
    public function syncHistorical(CarbonInterface $from, CarbonInterface $to, ?int $limit = null): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $limit ??= (int) config('telemetry.clickhouse.historical_rows_per_chunk', 80_000);
        $limit = max(1, min(2_000_000, $limit));

        $where = $this->buildTimeWhereRange($from, $to);

        return $this->runHistoricalPagedImport($where, $limit);
    }

    /**
     * Historical backfill for one IMEI.
     *
     * @return int Imported row count
     */
    public function syncHistoricalForImei(string $imei, CarbonInterface $from, CarbonInterface $to, ?int $limit = null): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $limit ??= (int) config('telemetry.clickhouse.historical_rows_per_chunk', 80_000);
        $limit = max(1, min(2_000_000, $limit));

        $imei = $this->sanitizeImei($imei);
        $where = $this->buildTimeWhereRange($from, $to);
        $where .= ' AND '.$this->deviceColumnSql()." = '{$imei}'";

        return $this->runHistoricalPagedImport($where, $limit);
    }

    /**
     * @param  list<string|int|float>  $rawImeis
     * @return list<string>
     */
    public function sanitizeImeiList(array $rawImeis): array
    {
        $out = [];
        foreach ($rawImeis as $raw) {
            try {
                $out[] = $this->sanitizeImei((string) $raw);
            } catch (\InvalidArgumentException) {
                continue;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Incremental sync for many IMEIs (each IMEI keeps its own cursor).
     *
     * @param  list<string|int|float>  $imeis
     * @return int Total imported rows
     */
    public function syncIncrementalForImeis(array $imeis, ?int $limitPerImei = null): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $limitPerImei ??= (int) config('telemetry.clickhouse.incremental_rows_per_imei', 15_000);
        $pauseUs = ((int) config('telemetry.clickhouse.pause_ms_between_imei', 300)) * 1000;

        $total = 0;
        $list = $this->sanitizeImeiList($imeis);
        $lastIdx = count($list) - 1;
        foreach ($list as $idx => $imei) {
            $total += $this->syncIncrementalForImei($imei, $limitPerImei);
            if ($pauseUs > 0 && $idx < $lastIdx) {
                usleep($pauseUs);
            }
        }

        return $total;
    }

    /**
     * Historical backfill for many IMEIs (chunked IN list for fewer round-trips).
     *
     * @param  list<string|int|float>  $imeis
     * @return int Total imported rows
     */
    public function syncHistoricalForImeis(
        array $imeis,
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $limitPerChunk = null,
        ?int $chunkSize = null,
    ): int {
        if (! $this->isEnabled()) {
            return 0;
        }

        $limitPerChunk ??= (int) config('telemetry.clickhouse.historical_rows_per_chunk', 80_000);
        $limitPerChunk = max(1, min(2_000_000, $limitPerChunk));
        $chunkSize ??= (int) config('telemetry.clickhouse.historical_imei_chunk_size', 40);
        $chunkSize = max(1, min(500, $chunkSize));
        $pauseUs = ((int) config('telemetry.clickhouse.pause_ms_between_historical_chunks', 500)) * 1000;

        $imeis = $this->sanitizeImeiList($imeis);
        if ($imeis === []) {
            return 0;
        }

        $baseWhere = $this->buildTimeWhereRange($from, $to);
        $devCol = $this->deviceColumnSql();
        $total = 0;

        $chunks = array_chunk($imeis, $chunkSize);
        foreach ($chunks as $idx => $chunk) {
            $inList = implode(',', array_map(fn (string $i): string => "'{$i}'", $chunk));
            $where = $baseWhere." AND {$devCol} IN ({$inList})";
            $total += $this->runHistoricalPagedImport($where, $limitPerChunk);
            if ($pauseUs > 0 && $idx < count($chunks) - 1) {
                usleep($pauseUs);
            }
        }

        return $total;
    }

    /**
     * Page through ClickHouse using keyset (timestamp, device, lat, lng) until the batch is smaller than $pageLimit.
     *
     * @param  non-empty-string  $baseWhere  Time range (+ optional IMEI filters), without keyset clause
     */
    private function runHistoricalPagedImport(string $baseWhere, int $pageLimit): int
    {
        $maxPages = (int) config('telemetry.clickhouse.historical_max_pages', 50_000);
        $maxPages = max(100, min(500_000, $maxPages));

        $total = 0;
        $where = $baseWhere;

        $batchSize = (int) config('telemetry.clickhouse.json_each_row_stream_batch', 2500);

        for ($page = 0; $page < $maxPages; $page++) {
            $sql = $this->buildSelectSql($where, $pageLimit, true);
            $n = 0;
            $last = null;
            $this->client->consumeJsonEachRowBatches($sql, $batchSize, function (array $batch) use (&$total, &$n, &$last): void {
                $total += $this->importer->import($batch);
                $n += count($batch);
                $last = $batch[array_key_last($batch)];
            });
            if ($n === 0) {
                break;
            }

            if ($n < $pageLimit) {
                break;
            }

            if ($page === $maxPages - 1) {
                Log::warning('ClickHouse historical: reached historical_max_pages; window may be incomplete', [
                    'pages' => $maxPages,
                    'page_limit' => $pageLimit,
                ]);
                break;
            }

            if (! is_array($last)) {
                Log::warning('ClickHouse historical: cannot build keyset from last row, stopping pagination');

                break;
            }

            $keyset = $this->extractHistoricalKeysetFromLastRow($last);
            if ($keyset === null) {
                Log::warning('ClickHouse historical: cannot build keyset from last row, stopping pagination');

                break;
            }

            $where = '('.$baseWhere.') AND '.$this->buildHistoricalKeysetWhere($keyset);
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $row  Raw JSONEachRow from ClickHouse
     * @return array{ts: int|string, device_id: string, lat: float, lng: float, unix: bool}|null
     */
    private function extractHistoricalKeysetFromLastRow(array $row): ?array
    {
        $deviceId = $row['device_id'] ?? null;
        if (! is_string($deviceId) && ! is_numeric($deviceId)) {
            return null;
        }
        $deviceId = (string) $deviceId;
        if ($deviceId === '') {
            return null;
        }

        $tRaw = $row['event_at'] ?? $row['timestamp'] ?? null;
        if ($tRaw === null) {
            return null;
        }

        $unix = $this->isUnixTimestamp();
        if ($unix) {
            if (is_numeric($tRaw)) {
                $ts = (int) $tRaw;
            } else {
                try {
                    $ts = Carbon::parse((string) $tRaw, 'UTC')->timestamp;
                } catch (\Throwable) {
                    return null;
                }
            }
        } else {
            try {
                $ts = Carbon::parse((string) $tRaw, 'UTC')->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return null;
            }
        }

        if (! is_numeric($row['latitude'] ?? null) || ! is_numeric($row['longitude'] ?? null)) {
            return null;
        }

        return [
            'ts' => $ts,
            'device_id' => $deviceId,
            'lat' => (float) $row['latitude'],
            'lng' => (float) $row['longitude'],
            'unix' => $unix,
        ];
    }

    /**
     * Strictly-after predicate for the last row of the previous page (stable sort: time, device, lat, lng).
     *
     * @param  array{ts: int|string, device_id: string, lat: float, lng: float, unix: bool}  $ks
     */
    private function buildHistoricalKeysetWhere(array $ks): string
    {
        $tsCol = $this->timestampColumnSql();
        $preset = (string) config('telemetry.clickhouse.schema_preset', 'location');
        $devCol = $preset === 'legacy' ? 'device_id' : $this->deviceColumnSql();
        $devExpr = "toString({$devCol})";
        $d = $this->escapeClickHouseString($ks['device_id']);
        $l = $this->formatKeysetFloat($ks['lat']);
        $g = $this->formatKeysetFloat($ks['lng']);

        if ($ks['unix']) {
            $t = (int) $ks['ts'];

            return "(({$tsCol} > {$t}) OR ({$tsCol} = {$t} AND {$devExpr} > '{$d}') OR ({$tsCol} = {$t} AND {$devExpr} = '{$d}' AND (latitude, longitude) > ({$l}, {$g})))";
        }

        $t = $this->escapeClickHouseString((string) $ks['ts']);

        return "(({$tsCol} > '{$t}') OR ({$tsCol} = '{$t}' AND {$devExpr} > '{$d}') OR ({$tsCol} = '{$t}' AND {$devExpr} = '{$d}' AND (latitude, longitude) > ({$l}, {$g})))";
    }

    private function escapeClickHouseString(string $s): string
    {
        return str_replace("'", "''", $s);
    }

    private function formatKeysetFloat(float $v): string
    {
        $s = rtrim(rtrim(sprintf('%.9f', $v), '0'), '.');

        return $s === '' || $s === '-' ? '0' : $s;
    }

    /**
     * After historical backfill, advance the per-IMEI incremental cursor so the next incremental sync
     * does not scan the full ClickHouse range again (PostgreSQL upsert is idempotent but slow).
     *
     * @param  CarbonInterface  $exclusiveUpperBound  Same upper bound as historical query (exclusive).
     */
    public function advanceIncrementalCursorAfterHistorical(string $imei, CarbonInterface $exclusiveUpperBound): void
    {
        try {
            $imei = $this->sanitizeImei($imei);
        } catch (\InvalidArgumentException) {
            return;
        }

        $key = 'clickhouse:imei:'.$imei.':incremental';
        $maxRow = DeviceLocation::query()->where('device_id', $imei)->max('event_at');

        if ($maxRow !== null) {
            $cursorTime = Carbon::parse((string) $maxRow, 'UTC');
        } else {
            $cursorTime = $exclusiveUpperBound->copy()->utc()->subSecond();
        }

        $cursor = TelemetrySyncCursor::query()->firstOrCreate(
            ['cursor_key' => $key],
            ['last_event_at' => null]
        );

        if ($cursor->last_event_at === null || $cursorTime->gt($cursor->last_event_at)) {
            $cursor->last_event_at = $cursorTime;
            $cursor->save();
        }
    }

    /**
     * @param  list<string|int|float>  $rawImeis
     */
    public function advanceIncrementalCursorsAfterHistorical(array $rawImeis, CarbonInterface $exclusiveUpperBound): void
    {
        foreach ($this->sanitizeImeiList($rawImeis) as $imei) {
            $this->advanceIncrementalCursorAfterHistorical($imei, $exclusiveUpperBound);
        }
    }

    private function sanitizeImei(string $imei): string
    {
        $imei = preg_replace('/\D/', '', $imei) ?? '';

        return $imei !== '' ? $imei : throw new \InvalidArgumentException('Invalid IMEI.');
    }

    private function deviceColumnSql(): string
    {
        $col = (string) config('telemetry.clickhouse.device_id_column', 'imei');
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $col)) {
            return 'imei';
        }

        return $col;
    }

    private function timestampColumnSql(): string
    {
        $col = (string) config('telemetry.clickhouse.timestamp_column', 'timestamp');
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $col)) {
            return 'timestamp';
        }

        return $col;
    }

    private function isUnixTimestamp(): bool
    {
        return config('telemetry.clickhouse.timestamp_type', 'unix_seconds') === 'unix_seconds';
    }

    private function buildTimeWhereAfter(?CarbonInterface $after): string
    {
        $tsCol = $this->timestampColumnSql();
        if ($after === null) {
            return $this->isUnixTimestamp() ? "{$tsCol} > 0" : "{$tsCol} > '1970-01-01 00:00:00'";
        }

        if ($this->isUnixTimestamp()) {
            return sprintf('%s > %d', $tsCol, $after->copy()->utc()->timestamp);
        }

        $s = $after->copy()->utc()->format('Y-m-d H:i:s');

        return "{$tsCol} > '{$s}'";
    }

    private function buildTimeWhereRange(CarbonInterface $from, CarbonInterface $to): string
    {
        $tsCol = $this->timestampColumnSql();
        if ($this->isUnixTimestamp()) {
            $a = $from->copy()->utc()->timestamp;
            $b = $to->copy()->utc()->timestamp;

            return "({$tsCol} >= {$a} AND {$tsCol} < {$b})";
        }

        $fs = $from->copy()->utc()->format('Y-m-d H:i:s');
        $ts = $to->copy()->utc()->format('Y-m-d H:i:s');

        return "({$tsCol} >= '{$fs}' AND {$tsCol} < '{$ts}')";
    }

    /**
     * @param  non-empty-string  $whereClause  SQL boolean expression (no user input except sanitized imei above)
     * @param  bool  $historicalKeysetOrder  When true, append device + lat/lng to ORDER BY for stable keyset pagination
     */
    private function buildSelectSql(string $whereClause, int $limit, bool $historicalKeysetOrder = false): string
    {
        $table = (string) config('telemetry.clickhouse.locations_table');
        if (! preg_match('/^[a-zA-Z0-9_.]+$/', $table)) {
            $table = 'location';
        }
        $suffix = trim((string) config('telemetry.clickhouse.select_sql_suffix', ''));
        $preset = (string) config('telemetry.clickhouse.schema_preset', 'location');
        $tsCol = $this->timestampColumnSql();
        $orderBy = $tsCol.' ASC';
        if ($historicalKeysetOrder) {
            if ($preset === 'legacy') {
                $orderBy = "{$tsCol} ASC, toString(device_id) ASC, latitude ASC, longitude ASC";
            } else {
                $devCol = $this->deviceColumnSql();
                $orderBy = "{$tsCol} ASC, toString({$devCol}) ASC, latitude ASC, longitude ASC";
            }
        }

        if ($preset === 'legacy') {
            $select = <<<SQL
                SELECT
                    device_id,
                    {$tsCol} AS event_at,
                    latitude,
                    longitude,
                    speed,
                    battery,
                    gsm_signal,
                    odometer,
                    ignition
                FROM {$table}
                WHERE {$whereClause}
                ORDER BY {$orderBy}
                LIMIT {$limit}
                FORMAT JSONEachRow
                SQL;
        } else {
            $devCol = $this->deviceColumnSql();
            $speedCol = (string) config('telemetry.clickhouse.speed_column', 'gpsSpeed');
            if (! preg_match('/^[a-zA-Z0-9_]+$/', $speedCol)) {
                $speedCol = 'gpsSpeed';
            }

            $select = <<<SQL
                SELECT
                    {$devCol} AS device_id,
                    {$tsCol} AS timestamp,
                    latitude,
                    longitude,
                    toFloat64({$speedCol}) AS speed,
                    io
                FROM {$table}
                WHERE {$whereClause}
                ORDER BY {$orderBy}
                LIMIT {$limit}
                FORMAT JSONEachRow
                SQL;
        }

        return trim($select.($suffix !== '' ? ' '.$suffix : ''));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function mergeMaxEventAtFromRow(?Carbon $max, array $row): ?Carbon
    {
        $t = $row['event_at'] ?? $row['timestamp'] ?? null;
        if ($t === null) {
            return $max;
        }
        try {
            if (is_numeric($t)) {
                $c = Carbon::createFromTimestampUTC((int) $t);
            } else {
                $c = Carbon::parse((string) $t, 'UTC');
            }
        } catch (\Throwable) {
            return $max;
        }

        if ($max === null || $c->gt($max)) {
            return $c;
        }

        return $max;
    }
}
