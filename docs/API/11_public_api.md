# Public API

## Authentication (SPA)

Sanctum cookie / bearer token as configured. Register and login under `/api/auth/*`.

| Method | Path | Notes |
|--------|------|--------|
| POST | `/api/auth/register` | Body: `name`, `email`, `password`, `password_confirmation`, `role` (`media_owner` \| `advertiser`) |
| POST | `/api/auth/login` | |
| POST | `/api/auth/logout` | `auth:sanctum` |
| GET | `/api/auth/me` | `auth:sanctum` |

---

## Platform (MVP Laravel API)

### Public

| Method | Path | Notes |
|--------|------|--------|
| GET | `/api/content-blocks` | Active content blocks. Optional `keys=key1,key2`. |

### Media owner (`auth:sanctum`, role `media_owner`)

| Method | Path | Notes |
|--------|------|--------|
| GET | `/api/media-owner/dashboard` | |
| GET | `/api/media-owner/vehicles` | |
| POST | `/api/media-owner/vehicles` | |
| GET | `/api/media-owner/vehicles/{vehicle}` | |
| PUT | `/api/media-owner/vehicles/{vehicle}` | |
| POST | `/api/media-owner/vehicles/{vehicle}/image` | Multipart `file` |
| GET | `/api/media-owner/campaigns` | |
| GET | `/api/media-owner/campaigns/{campaign}` | Includes `vehicles` (pivot: `placement_size_class`, `agreed_price`, …) scoped to MO fleet |
| GET | `/api/media-owner/campaigns/{campaign}/proofs` | JSON `data[]`: `id`, `vehicle_id`, `status`, `comment`, `url`, `created_at`, `vehicle` — only proofs for MO’s vehicles |
| POST | `/api/media-owner/campaigns/{campaign}/proofs` | Multipart `file` + `vehicle_id` (vehicle must be on campaign) |
| GET | `/api/media-owner/earnings` | |

### Advertiser (`auth:sanctum`, role `advertiser`)

| Method | Path | Notes |
|--------|------|--------|
| GET | `/api/advertiser/dashboard` | Metrics payload includes `source` (`mock`, `http`, `mock_fallback`) when telemetry URL optional |
| GET | `/api/advertiser/campaigns` | List with `campaign_vehicles.vehicle` |
| POST | `/api/advertiser/campaigns` | |
| GET | `/api/advertiser/campaigns/{campaign}` | Includes `campaign_vehicles` + nested `vehicle`, and **`vehicle_telemetry`** (per `vehicle_id`: impressions, km, drive/park hours; drive time from **`stop_sessions`** when available; window = campaign dates when set). |
| PUT | `/api/advertiser/campaigns/{campaign}` | |
| POST | `/api/advertiser/campaigns/{campaign}/vehicles` | JSON: `vehicle_id`, `placement_size_class` |
| DELETE | `/api/advertiser/campaigns/{campaign}/vehicles/{campaignVehicle}` | Pivot id |
| GET | `/api/advertiser/campaigns/{campaign}/proofs` | Read-only list: same `data[]` shape as media-owner. Upload is **`POST /api/media-owner/campaigns/{campaign}/proofs`** only. |
| GET | `/api/advertiser/vehicles` | Query `per_page` (1–200) |
| GET | `/api/advertiser/vehicles/{vehicle}` | |
| GET | `/api/advertiser/heatmap` | Query: `campaign_id`, **required** `date_from` and `date_to`, optional `vehicle_id`, **`mode`**: `driving` \| `parking`, optional bbox `south`/`west`/`north`/`east` + `zoom`, **`normalization`**: `max` \| `p95` \| `p99` (default **p95**). Response: **`map`** (`points`, `buckets`, `mode`, `heatmap_motion`, `normalization`, `heatmap_rollup`), **`debug`** (e.g. `intensity_gamma`, `intensity_stopped_power`, caps, viewport sample counts), **`summary_metrics`** (period KPIs: `impressions`, `driving_distance_km`, `driving_time_hours`, `parking_time_hours`, `data_source`, `is_estimated` — independent of viewport and map mode). See `docs/ARCHITECTURE/06_heatmap_rollup.md`. |
| GET | `/api/advertiser/pricing` | |
| GET | `/api/advertiser/profile-discounts` | |

---

## Telemetry pipeline (`auth:sanctum`)

Implemented in Laravel; data lands in PostgreSQL after ClickHouse sync (see `docs/ARCHITECTURE/05_telemetry_pipeline.md`).

| Method | Path | Notes |
|--------|------|--------|
| GET | `/api/telemetry/locations/raw` | Query: `imei`, `date_from`, `date_to`. Rows from `device_locations`. **Advertiser:** IMEI on own campaign(s). **Media owner:** own fleet. |
| GET | `/api/telemetry/impressions/daily` | **Advertiser only.** Optional `campaign_id`, `date_from`, `date_to`. |
| GET | `/api/telemetry/impressions/zones` | **Advertiser only.** Optional `campaign_id`, `date_from`, `date_to`. |
| GET | `/api/telemetry/vehicles` | IMEI list for telemetry: advertiser → vehicles on own campaigns; media owner → own fleet. |
| GET | `/api/telemetry/campaigns` | Advertiser → own campaigns; media owner → campaigns involving their vehicles. |

**Advertiser dashboard:** по умолчанию метрики из БД (`daily_impressions`, при отсутствии строк — оценка impressions по `device_locations` × `TELEMETRY_IMPRESSION_MULTIPLIER`). Внешний API (опционально): `TELEMETRY_METRICS_URL`, `TELEMETRY_METRICS_TOKEN` — при ошибке HTTP используется mock-fallback (см. `backend/config/telemetry.php`).

**ClickHouse collector:** `TELEMETRY_CLICKHOUSE_ENABLED`, `TELEMETRY_CLICKHOUSE_URL`, `TELEMETRY_CLICKHOUSE_DATABASE`, `TELEMETRY_CLICKHOUSE_USER`, `TELEMETRY_CLICKHOUSE_PASSWORD`, `TELEMETRY_CLICKHOUSE_LOCATIONS_TABLE`.

**Heatmap driver:** `TELEMETRY_HEATMAP_DRIVER` = `database` \| `mock`.
