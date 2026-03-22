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

Scheduler (see `backend/bootstrap/app.php`): `telemetry:scheduler-tick` every minute (incremental when due per admin interval, default **10** min); build + aggregate for **yesterday** nightly.

---

## Heatmap

When `TELEMETRY_HEATMAP_DRIVER=database` (default in `config/telemetry.php`), `GET /api/advertiser/heatmap` reads **aggregated** points from `device_locations` for campaign vehicles (`device_id` = IMEI). Metrics prefer `daily_impressions` in range, else raw sample counts.

Points are **0.001° grid buckets** (count `w` per cell). API `intensity` is \((w / \mathrm{max}_w)^\gamma\) capped at 1. **γ** is set in **Telematics → Heatmap → Heatmap display** (stored in `platform_settings`, key `telemetry_heatmap_intensity_gamma`). If unset, fallback is `telemetry.heatmap.intensity_gamma` / env `TELEMETRY_HEATMAP_INTENSITY_GAMMA` (default **1.55**). γ = 1 is linear; γ > 1 dims mid-density cells so the **highest-concentration** buckets read hotter on the map. Admin and advertiser API share `HeatmapBucketIntensity` / `TelemetryHeatmapConfig`.

Set `TELEMETRY_HEATMAP_DRIVER=mock` for fixed demo points (no DB data).

---

## HTTP API (authenticated)

See `docs/API/11_public_api.md` — routes under `/api/telemetry/*`.

---

## Operational checklist

1. Ensure vehicles carry correct **IMEI** = ClickHouse `device_id`.  
2. Create **geo_zones** (Filament/seeder/SQL) for markets you attribute.  
3. Enable ClickHouse env vars (`TELEMETRY_CLICKHOUSE_*`, `TELEMETRY_CH_*`); smoke test: **`php artisan telemetry:test-clickhouse`**. Override CH column names if needed (`TELEMETRY_CH_TIMESTAMP_COLUMN`, etc.). Run **historical** sync once, then rely on **incremental**.  
4. Run **build-stop-sessions** and **aggregate-daily** after sync (or use scheduler).  
