# Heatmap rollup architecture

## Overview

Raw telemetry remains in `device_locations`. Map heatmaps for long date ranges are served from pre-aggregated rows in `heatmap_cells_daily` when the client sends **viewport bounds** (`south`, `west`, `north`, `east`) and **map zoom** (`zoom`).

Supported map modes (product): **driving** (advertiser) / **moving** (admin UI) and **parking** / **stopped**. Combined driving+parking on one map is not supported.

## Classification (MVP)

Aggregation uses the same SQL rules as `App\Services\Telemetry\DeviceLocationHeatmapBuckets` (speed vs `telemetry.parking_speed_kmh_max` and `ignition`). This is **point-based** parking detection.

Future: optional session-weighted parking using `stop_sessions` (see `docs/ARCHITECTURE/05_telemetry_pipeline.md`).

## Bucket tiers

`App\Services\Telemetry\HeatmapBucketStrategy` maps Leaflet zoom to a **tier index** and a **decimal precision** for `ROUND(latitude/longitude, decimals)`. Tiers are configured in `config('telemetry.heatmap.rollup.zoom_tiers')`.

## Write path

- `php artisan heatmap:aggregate --from=Y-m-d --to=Y-m-d` (optional `--day=`, `--tier=`, `--mode=driving|parking`, or `--all-modes`).
- `php artisan heatmap:aggregate-day Y-m-d` — thin wrapper around `--day=`.
- `php artisan heatmap:rebuild --from=… --to=…` — same as aggregate for a range.
- Service: `App\Services\Telemetry\HeatmapAggregationService` (PostgreSQL only for the INSERT…SELECT aggregation).
- Idempotency: for each `(day, zoom_tier, mode)` the service deletes existing rows then inserts fresh aggregates.

## Read path

- `App\Services\Telemetry\HeatmapRollupQueryService` runs `SUM(samples_count)` grouped by `lat_bucket`, `lng_bucket` with filters on date range, mode, tier, `device_id` IN (…), and bbox.
- Normalization uses existing `HeatmapIntensityNormalizer` (p95/p99/max caps, moving gamma, parking power curve).

## Fallback

If `TELEMETRY_HEATMAP_LEGACY_FALLBACK_NO_VIEWPORT` is true (default), missing viewport parameters fall back to on-the-fly `GROUP BY` on `device_locations` (expensive on large ranges). Set to `false` in production to require bbox+zoom.

## Related env / config

- `TELEMETRY_HEATMAP_ROLLUP_READ` — enable rollup reads (default true).
- `TELEMETRY_HEATMAP_LEGACY_FALLBACK_NO_VIEWPORT` — allow legacy path without viewport (default true for dev).
- `telemetry.heatmap.rollup.zoom_tiers` — tier definitions.

## Ongoing operations

- **New telemetry** in `device_locations` must be rolled into `heatmap_cells_daily` by running `heatmap:aggregate` (or `heatmap:aggregate-day`).
- **Automated (when ClickHouse telemetry sync is enabled):** `telemetry:scheduler-tick` runs every minute; at the admin-configured **`aggregateDailyAt`** time (same slot as `telemetry:aggregate-daily`), it also runs `heatmap:aggregate --from=yesterday --to=yesterday --all-modes` on **PostgreSQL** once per UTC calendar day (cache key `telemetry_tick_heatmap_rollup_{date}`). Ensure the server cron runs `* * * * * php artisan schedule:run` (see Laravel docs).
- **Manual / extra backfill:** e.g. `php artisan heatmap:aggregate-day $(date -u +%F)` or a `--from`/`--to` range with `--all-modes`.
- Re-running `heatmap:aggregate` for a range is **idempotent** (delete-then-insert per day/tier/mode).
- **Advertiser heatmap JSON** includes map buckets from rollup (viewport + zoom) and **KPI block** (`impressions`, `driving_distance_km`, `driving_time_hours`, `parking_time_hours`) from `resolveMetrics()` over the same `date_from` / `date_to` and vehicles — i.e. daily_impressions / stop_sessions / GPS estimates, not the rollup cells. If KPIs look wrong after changing dates, hard-refresh once; the SPA also aborts superseded fetches so pan/zoom cannot overwrite a newer period’s response.
