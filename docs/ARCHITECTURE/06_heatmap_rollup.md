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
- `TELEMETRY_HEATMAP_ADVERTISER_RAW_SUMMARY_FALLBACK` — allow legacy full `device_locations` scan for advertiser **summary_metrics** when daily rows are missing (default false).
- `telemetry.heatmap.rollup.zoom_tiers` — tier definitions.

## Ongoing operations

- **New telemetry** in `device_locations` must be rolled into `heatmap_cells_daily` by running `heatmap:aggregate` (or `heatmap:aggregate-day`).
- **Automated (when ClickHouse telemetry sync is enabled):** `telemetry:scheduler-tick` runs **hourly** (with `0 * * * *` cron for `schedule:run`); after the admin-configured **`aggregateDailyAt`** UTC slot has passed for the calendar day, it also runs `heatmap:aggregate --from=yesterday --to=yesterday --all-modes` on **PostgreSQL** once per UTC calendar day (cache key `telemetry_tick_heatmap_rollup_{date}`). For sub-hourly incremental ClickHouse pulls, use `* * * * *` cron and `everyMinute()` in `bootstrap/app.php`.
- **Manual / extra backfill:** e.g. `php artisan heatmap:aggregate-day $(date -u +%F)` or a `--from`/`--to` range with `--all-modes`.
- Re-running `heatmap:aggregate` for a range is **idempotent** (delete-then-insert per day/tier/mode).

## Advertiser API: map vs summary KPIs (`GET /api/advertiser/heatmap`)

Clients must send **`date_from`** and **`date_to`** (inclusive calendar days); requests without both are rejected with **422** so KPIs never silently aggregate “all time”.

The JSON response separates **viewport-dependent** data from **period totals**:

| Section | Purpose | Depends on |
|--------|---------|------------|
| `map` | `points`, `buckets`, `mode`, `heatmap_motion`, `normalization`, `heatmap_rollup` | bbox, zoom, `mode` (driving/parking **layer**), dates, vehicles |
| `debug` | `intensity_gamma`, `intensity_stopped_power`, caps, `location_samples`, `location_samples_viewport`, `heatmap_zoom_tier`, optional errors | Same as map (internal / diagnostics) |
| `summary_metrics` | `impressions`, `driving_distance_km`, `driving_time_hours`, `parking_time_hours`, `data_source`, `is_estimated` | **Campaign + date range + selected vehicles only** — not bbox, zoom, or map mode |

**Product rule:** Bottom KPI cards always show **full activity** for the selected campaign/vehicles/period. Toggling driving vs parking changes only the **map layer** in `map`, not the semantics of `summary_metrics`.

**Sources** (`summary_metrics.data_source`): `daily_impressions` when aggregates cover the period; `daily_impressions_estimated` when GPS gap-fill from `device_locations` was used (`is_estimated: true`); `insufficient_aggregates` when there are no usable daily rows and **raw fallback is disabled** (default) — KPI numeric fields are `null`. Optional escape hatch: `TELEMETRY_HEATMAP_ADVERTISER_RAW_SUMMARY_FALLBACK=true` restores the legacy full `device_locations` scan for KPIs only (marked `device_locations_*`, `is_estimated: true`).

Shared filter context: `App\Services\Telemetry\HeatmapPageQuery`. Map reads all fields; `HeatmapSummaryMetricsService` ignores bbox/zoom and map `mode`.
