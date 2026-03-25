# Telemetry Data Pipeline

## Source

ClickHouse

Host example:

178.63.17.153:8123

Data source contains GPS telemetry.

---

## Data Flow

ClickHouse  
↓  
Laravel Collector  
↓  
PostgreSQL  

---

## Storage (PostgreSQL)

### `device_locations`

Canonical storage after sync. Maps to pipeline field **timestamp** as column **`event_at`** (UTC, microsecond precision).

| Column | Notes |
|--------|--------|
| `device_id` | String; **must match `vehicles.imei`** for joins to campaigns |
| `event_at` | Pipeline field *timestamp* |
| `latitude`, `longitude` | WGS84 |
| `speed`, `battery`, `gsm_signal`, `odometer`, `ignition` | Optional |
| `extra_json` | Optional JSON |

Unique (`device_id`, `event_at`) for idempotent upserts.

### `telemetry_sync_cursors`

Watermark for **incremental** ClickHouse pulls (`cursor_key`, `last_event_at`).

### `geo_zones`

BBox zones (MVP) for attribution: `min_lat`, `max_lat`, `min_lng`, `max_lng`, `code`, `active`.

### `stop_sessions`

Derived from `device_locations`: alternating **parking** vs **driving** segments (speed / ignition heuristics). Stores centroid, `point_count`, time range.

### `stop_session_zone`

Many-to-many: parking `stop_sessions` attributed to `geo_zones` whose bbox contains the session centroid.

### `daily_impressions`

Per calendar day, **campaign × vehicle**: `impressions` (sample count × multiplier), `driving_distance_km`, `parking_minutes`.

### `daily_zone_impressions`

Per day, **zone × campaign**: rollup from attributed parking sessions (`point_count`).

---

## Sync

Two modes (Artisan):

| Command | Purpose |
|---------|---------|
| `php artisan telemetry:sync-incremental` | Rows with CH `timestamp` **greater than** global cursor; batch size = `--limit` or `TELEMETRY_CH_GLOBAL_INCREMENTAL_ROWS` |
| `php artisan telemetry:sync-historical --from=… --to=…` | Window backfill; does **not** rewind incremental cursor. Repeats ClickHouse queries with **keyset pagination** (timestamp → device → lat/lng) until the window is exhausted or `TELEMETRY_CH_HISTORICAL_MAX_PAGES` is hit (~`rows_per_chunk × max_pages` rows max). |

Requires `TELEMETRY_CLICKHOUSE_ENABLED=true` and ClickHouse HTTP settings in `.env` / `config/telemetry.php`. На деплое **`deploy/post-pull.sh`** вызывает **`php artisan telemetry:ensure-env`**, чтобы в `backend/.env` появились недостающие ключи из **`deploy/telemetry.env.fragment`** (уже заданные значения не перезаписываются).

**Smoke test (no sync):** `php artisan telemetry:test-clickhouse` — uses `TELEMETRY_CLICKHOUSE_URL` (optional `--url=http://178.63.17.153:8123`).

Default CH query expects table columns: `device_id`, `timestamp`, `latitude`, `longitude`, `speed`, `battery`, `gsm_signal`, `odometer`, `ignition`. Override via `TELEMETRY_CLICKHOUSE_SELECT_SUFFIX` or change `locations_table` / database.

---

## Analytics Pipeline (Artisan)

| Command | Purpose |
|---------|---------|
| `php artisan telemetry:build-stop-sessions [--date=YYYY-MM-DD]` | Rebuilds sessions for that **UTC calendar day** (default: yesterday), then zone attribution |
| `php artisan telemetry:aggregate-daily [--date=YYYY-MM-DD]` | Rebuilds `daily_impressions` and `daily_zone_impressions` for that day |

**Order of operations (typical day):**

1. `telemetry:sync-incremental` (or historical)  
2. `telemetry:build-stop-sessions --date=yesterday`  
3. `telemetry:aggregate-daily --date=yesterday`  

Scheduler (see `backend/bootstrap/app.php`): `telemetry:scheduler-tick` **hourly** by default (with hourly system cron); incremental pull runs at most once per tick (pair with admin interval); build + aggregate for **yesterday** after the configured UTC slots (compatible with hourly `schedule:run`).

---

## Heatmap

When `TELEMETRY_HEATMAP_DRIVER=database` (default in `config/telemetry.php`), `GET /api/advertiser/heatmap` reads **aggregated** points from `device_locations` for campaign vehicles (`device_id` = IMEI). **Metrics** use `daily_impressions` rows in the selected date range, scoped to the same vehicles as the map (optional `vehicle_id`); driving/parking minutes from `stop_sessions` when present; if daily rows lack **km** and/or **parking minutes**, the API fills gaps with a **single streaming pass** over `device_locations`: driving km (haversine when speed > 5 or unknown) and parking duration between consecutive “stopped” pings (same rule as `StopSessionBuilder`: `ignition=false` or `speed ≤ TELEMETRY_PARKING_SPEED_MAX`), ignoring gaps longer than `telemetry.heatmap.max_parking_segment_seconds` (env `TELEMETRY_HEATMAP_MAX_PARKING_SEGMENT_SECONDS`, default 2h). If there are no daily rows, metrics fall back to raw location counts × multiplier plus the same GPS estimates and sessions.

**Map buckets** are **0.001°** cells with **separate** `w_moving` and `w_stopped` counts (shared SQL via `DeviceLocationHeatmapBuckets`). **Moving** intensity: `min(1, w/cap)` then **γ** (`TelemetryHeatmapConfig`, default **1.55**) with the same **normalization** `max` \| `p95` \| `p99` (`HeatmapIntensityNormalizer::capFromWeights`). **Stopped/parking** intensity: same cap, then fixed **`ratio^0.7`** (`normalizeStopped`) to lift mid-density for a Google-style city-wide density read (γ does not apply to stopped). Admin + advertiser: Viridis-style moving heat; parking = continuous **green → yellow → orange → red** with wider radius/blur. **γ** is set in **Telematics → Heatmap → Heatmap display** (key `telemetry_heatmap_intensity_gamma`); env `TELEMETRY_HEATMAP_INTENSITY_GAMMA`.

Set `TELEMETRY_HEATMAP_DRIVER=mock` for fixed demo points (no DB data).

**Heatmap performance (large date ranges):** bucket queries use **sargable** `event_at` bounds via `DeviceLocationEventAtRange` (plain range on the indexed column, not `WHERE DATE(event_at) …`). Per-cell **rank %** is computed in **O(n log n)** (`rankPercentBelowBatch`) instead of O(n²). Admin drops an extra **COUNT(\*)** when the same total is implied by summed bucket weights. When metrics fall back to raw `device_locations`, **one cursor pass** yields row count + km + parking minutes (no separate `COUNT` + estimate). **`stop_sessions` minutes** for heatmap/campaign rollup use **one SQL aggregate** per kind (and `GROUP BY device_id` for per-vehicle rollup) on PostgreSQL/MySQL instead of one cursor per IMEI. Optional **`TELEMETRY_HEATMAP_MAX_DATE_RANGE_DAYS`** rejects over-long windows with **422** (see `HeatmapRequestDateRange`). Further gains: keep **`daily_impressions`** / **`stop_sessions`** populated so GPS streaming is skipped; optional composite index `(device_id, event_at)` if the planner still prefers a dedicated range index for multi-IMEI queries.

---

## HTTP API (authenticated)

See `docs/API/11_public_api.md` — routes under `/api/telemetry/*`.

---

## Operational checklist

1. Ensure vehicles carry correct **IMEI** = ClickHouse `device_id`.  
2. Create **geo_zones** (Filament/seeder/SQL) for markets you attribute.  
3. Enable ClickHouse env vars (`TELEMETRY_CLICKHOUSE_*`, `TELEMETRY_CH_*`); smoke test: **`php artisan telemetry:test-clickhouse`**. Override CH column names if needed (`TELEMETRY_CH_TIMESTAMP_COLUMN`, etc.). Run **historical** sync once, then rely on **incremental**.  
4. Run **build-stop-sessions** and **aggregate-daily** after sync (or use scheduler).  
