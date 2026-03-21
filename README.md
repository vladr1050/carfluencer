# Carfluencer × Evo.ad — MVP Monorepo

Full-stack MVP for advertising placements on vehicles (initial fleet: Carguru).  
**All application code and docs in this repository are in English.**

## Structure

| Path | Description |
|------|-------------|
| `backend/` | Laravel 13 API + Filament admin + Sanctum auth |
| `design/Files/` | React UI prototype (Figma export) — **reference**; `frontend/` tracks this UI |
| `docs/` | Product & architecture documentation |
| `frontend/` | Vite + React portals — **same UI/UX as `design/Files`**, API-integrated (see `frontend/README.md`) |

## Backend (Laravel)

### Requirements

- PHP 8.4+
- Composer
- SQLite (default) or PostgreSQL for production

### Setup

```bash
cd backend
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

- **API base:** `http://localhost:8000/api`
- **Filament admin:** `http://localhost:8000/admin`  
  - After seed: `admin@carfluencer.test` / `password`

### Demo API users (from seeder)

- Media owner: `media@carguru.test` / `password`
- Advertiser: `advertiser@brand.test` / `password`

### Key API routes

- `GET /api/content-blocks` — **public**; active CMS blocks for portals (`?keys=a,b` optional)
- `POST /api/auth/register` — roles: `media_owner`, `advertiser` only (requires `company_name`)
- `POST /api/auth/login` — returns Bearer token
- `GET /api/auth/me` — Sanctum
- Media owner: `/api/media-owner/*` including `GET /api/media-owner/campaigns/{campaign}`, `POST /api/media-owner/vehicles/{vehicle}/image` (multipart `image`), `POST /api/media-owner/campaigns/{campaign}/proofs` (multipart `file` + `vehicle_id`)
- Advertiser: `/api/advertiser/*` including `POST /api/advertiser/campaigns/{campaign}/vehicles` (attach vehicle + placement size), `DELETE .../vehicles/{campaignVehicle}`, `GET /api/advertiser/heatmap?campaign_id=...`

### CORS

Set `CORS_ALLOWED_ORIGINS` in `backend/.env` to a comma-separated list of frontend origins (e.g. `http://localhost:5173`). If unset, `config/cors.php` allows all origins (`*`).

### Tests

```bash
cd backend && php artisan test
```

### Telemetry / heatmap

Full pipeline: **ClickHouse → PostgreSQL** (`device_locations`), **stop sessions**, **zone attribution**, **daily impressions** — see `docs/ARCHITECTURE/05_telemetry_pipeline.md`.

- **Artisan:** `telemetry:sync-incremental`, `telemetry:sync-historical`, `telemetry:build-stop-sessions`, `telemetry:aggregate-daily` (scheduled in `backend/bootstrap/app.php`).
- **API:** `/api/telemetry/*` (authenticated) — see `docs/API/11_public_api.md`.
- **Heatmap:** `TELEMETRY_HEATMAP_DRIVER=database` (default) reads `device_locations`; use `mock` for demo points without data.

### PostgreSQL (production)

Set in `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=carfluencer
DB_USERNAME=...
DB_PASSWORD=...
```

## Frontend (React)

Production SPA lives in `frontend/` (Vite + React + TypeScript).

```bash
cd frontend
cp .env.example .env
npm install
npm run dev
```

Set `VITE_API_URL=http://localhost:8000` (or your API URL). The app uses Bearer tokens from `POST /api/auth/login`.

Advertiser area includes **Heatmap** (OpenStreetMap + Leaflet) and **Pricing** (S/M/L/XL placement rates). For a richer UI, merge components from `design/Files/` into `frontend/src/`. See `frontend/README.md`.

## Production deployment

| Resource | Purpose |
|----------|---------|
| **`deploy/setup-ubuntu-server.sh`** | One-shot VPS: PHP 8.4, Postgres, Nginx, `.env`, migrations, **Supervisor** (`carfluencer-queue`), **cron** (`schedule:run`) |
| **`deploy/post-pull.sh`** | After `git pull` / GitHub Actions deploy |
| **`deploy/frontend-build-production.sh`** | Build React portals (`frontend/`) → upload `frontend/dist` to the server |
| **`deploy/README.md`** | Index of nginx / supervisor / cron templates |
| **`docs/DEPLOY/12_vps_production.md`** | Full VPS checklist (TLS, firewall, first admin) |
| **`docs/DEPLOY/15_github_actions.md`** | CI/CD secrets and workflows |

Env template: **`backend/.env.production.example`**. First Filament user on server: `php artisan make:filament-user` (as `www-data` if `.env` is group-readable). See also **`backend/docs/DEPLOYMENT.md`**.

## Implementation status

See `docs/IMPLEMENTATION_STATUS.md` for what is done vs remaining (additional Filament resources, full React integration, production telemetry).

## Business rule: S / M / L / XL

These codes are **ad placement size classes** on a vehicle, **not** vehicle size categories. Pricing is defined in `ad_placement_policies`; each `campaign_vehicles` row stores `placement_size_class`.
