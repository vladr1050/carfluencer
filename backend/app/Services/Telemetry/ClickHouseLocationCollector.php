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
    public function syncIncremental(int $limit = 100_000): int
    {
        if (! $this->isEnabled()) {
            Log::info('ClickHouse collector skipped (TELEMETRY_CLICKHOUSE_ENABLED=false).');

            return 0;
        }

        $key = (string) config('telemetry.cursor_incremental');
        $cursor = TelemetrySyncCursor::query()->firstOrCreate(
            ['cursor_key' => $key],
            ['last_event_at' => null]
        );

        $where = $this->buildTimeWhereAfter($cursor->last_event_at);
        $sql = $this->buildSelectSql($where, $limit);
        $rows = $this->client->queryJsonEachRow($sql);
        $imported = $this->importer->import($rows);

        $max = $this->maxEventAtFromRows($rows);
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
    public function syncIncrementalForImei(string $imei, int $limit = 200_000): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $imei = $this->sanitizeImei($imei);
        $key = 'clickhouse:imei:'.$imei.':incremental';
        $cursor = TelemetrySyncCursor::query()->firstOrCreate(
            ['cursor_key' => $key],
            ['last_event_at' => null]
        );

        $where = $this->buildTimeWhereAfter($cursor->last_event_at);
        $where .= ' AND '.$this->deviceColumnSql()." = '{$imei}'";
        $sql = $this->buildSelectSql($where, $limit);
        $rows = $this->client->queryJsonEachRow($sql);
        $imported = $this->importer->import($rows);

        $max = $this->maxEventAtFromRows($rows);
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
    public function syncHistorical(CarbonInterface $from, CarbonInterface $to, int $limit = 500_000): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $where = $this->buildTimeWhereRange($from, $to);
        $sql = $this->buildSelectSql($where, $limit);
        $rows = $this->client->queryJsonEachRow($sql);

        return $this->importer->import($rows);
    }

    /**
     * Historical backfill for one IMEI.
     *
     * @return int Imported row count
     */
    public function syncHistoricalForImei(string $imei, CarbonInterface $from, CarbonInterface $to, int $limit = 500_000): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $imei = $this->sanitizeImei($imei);
        $where = $this->buildTimeWhereRange($from, $to);
        $where .= ' AND '.$this->deviceColumnSql()." = '{$imei}'";
        $sql = $this->buildSelectSql($where, $limit);
        $rows = $this->client->queryJsonEachRow($sql);

        return $this->importer->import($rows);
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
    public function syncIncrementalForImeis(array $imeis, int $limitPerImei = 200_000): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $total = 0;
        foreach ($this->sanitizeImeiList($imeis) as $imei) {
            $total += $this->syncIncrementalForImei($imei, $limitPerImei);
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
        int $limitPerChunk = 500_000,
        int $chunkSize = 200,
    ): int {
        if (! $this->isEnabled()) {
            return 0;
        }

        $imeis = $this->sanitizeImeiList($imeis);
        if ($imeis === []) {
            return 0;
        }

        $baseWhere = $this->buildTimeWhereRange($from, $to);
        $devCol = $this->deviceColumnSql();
        $total = 0;

        foreach (array_chunk($imeis, max(1, $chunkSize)) as $chunk) {
            $inList = implode(',', array_map(fn (string $i): string => "'{$i}'", $chunk));
            $where = $baseWhere." AND {$devCol} IN ({$inList})";
            $sql = $this->buildSelectSql($where, $limitPerChunk);
            $rows = $this->client->queryJsonEachRow($sql);
            $total += $this->importer->import($rows);
        }

        return $total;
    }

    /**
     * After historical backfill, advance the per-IMEI incremental cursor so the next incremental sync
     * does not scan the full ClickHouse range again (PostgreSQL upsert is idempotent but slow).
     *
     * @param  \Carbon\CarbonInterface  $exclusiveUpperBound  Same upper bound as historical query (exclusive).
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
     */
    private function buildSelectSql(string $whereClause, int $limit): string
    {
        $table = (string) config('telemetry.clickhouse.locations_table');
        if (! preg_match('/^[a-zA-Z0-9_.]+$/', $table)) {
            $table = 'location';
        }
        $suffix = trim((string) config('telemetry.clickhouse.select_sql_suffix', ''));
        $preset = (string) config('telemetry.clickhouse.schema_preset', 'location');
        $tsCol = $this->timestampColumnSql();
        $orderCol = $tsCol;

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
                ORDER BY {$orderCol} ASC
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
                ORDER BY {$orderCol} ASC
                LIMIT {$limit}
                FORMAT JSONEachRow
                SQL;
        }

        return trim($select.($suffix !== '' ? ' '.$suffix : ''));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function maxEventAtFromRows(array $rows): ?Carbon
    {
        $max = null;
        foreach ($rows as $row) {
            $t = $row['event_at'] ?? $row['timestamp'] ?? null;
            if ($t === null) {
                continue;
            }
            try {
                if (is_numeric($t)) {
                    $c = Carbon::createFromTimestampUTC((int) $t);
                } else {
                    $c = Carbon::parse((string) $t, 'UTC');
                }
            } catch (\Throwable) {
                continue;
            }
            if ($max === null || $c->gt($max)) {
                $max = $c;
            }
        }

        return $max;
    }
}
