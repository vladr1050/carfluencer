# Implementation status — Carfluencer MVP build

Last updated: 2026-03-20 (policies, MO earnings & advertiser discounts UI)

## Done

- Laravel application under `backend/` with **Sanctum** API auth and **Filament v5** admin (`/admin`, admin users only).
- Full domain schema: users (roles), profiles, **vehicles** (IMEI), **ad placement policies** (S/M/L/XL), **campaigns**, **campaign_vehicles** (placement size on pivot), proofs, platform settings, content blocks.
- **REST API** for auth, media owner (dashboard, vehicles CRUD, vehicle image upload, campaigns list + campaign detail, earnings, **campaign proof upload**), advertiser (dashboard, campaigns CRUD, attach/detach campaign vehicles, vehicles browse, heatmap, pricing, profile discounts).
- **Public API:** `GET /api/content-blocks` (optional `keys` filter) — wired on the React **home** page.
- **CORS** configurable via `CORS_ALLOWED_ORIGINS` (comma-separated); defaults to `*` when unset.
- **Filament**: full resources + campaign vehicles relation manager + admin stats widget.
- **Telemetry**: ClickHouse → PostgreSQL `device_locations` collector; `stop_sessions` + geo zone attribution + `daily_impressions` / `daily_zone_impressions`; Artisan commands + scheduler; `GET /api/telemetry/*`; heatmap driver `database` \| `mock` (`config/telemetry.php`). See `docs/ARCHITECTURE/05_telemetry_pipeline.md`.
- **React SPA** in `frontend/` (Vite + TS): **MediaOwnerNav** + **Earnings** page (`/media-owner/earnings`); **AdvertiserNav** + **Discounts** page (`/advertiser/discounts`); Leaflet heatmap + Pricing + content blocks on landing.
- **Laravel policies:** `VehiclePolicy`, `CampaignPolicy` (view/update/viewAnalytics), `CampaignVehiclePolicy` (delete); base `Controller` uses `AuthorizesRequests`; API uses `$this->authorize()` instead of inline `abort_if` where covered.
- **PHPUnit feature tests:** auth, roles, content blocks, proof upload, **policy** checks (`PolicyAuthorizationApiTest`), 12 tests (`php artisan test`).
- **UserFactory** states: `admin()`, `mediaOwner()`, `advertiser()`.
- Docs: root `README.md`, `backend/docs/DEPLOYMENT.md`, `docs/API/11_public_api.md`.

## Partial / next steps

| Area | Notes |
|------|--------|
| **UI polish** | Merge `design/Files/` styling; optional Mapbox/enterprise tiles instead of OSM. |
| **Advertiser dashboard metrics** | Replace placeholders when analytics/telemetry pipeline is connected. |
| **Advertiser proof upload** | Optional symmetry with media owner if product requires it. |
| **Laravel Policies** | Extend to admin API routes if you add JSON admin endpoints beyond Filament. |
| **PostgreSQL** | `DB_CONNECTION=pgsql` on staging/production. |

## Conventions

- **S / M / L / XL** = ad **placement** size on the vehicle (`campaign_vehicles.placement_size_class`), not vehicle category.
- **IMEI** on `vehicles` links to telemetry.
