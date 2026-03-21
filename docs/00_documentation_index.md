# Carfluencer x Evo.ad — MVP Documentation

Version: MVP v1  
Status: Development  
Stack: Laravel + Filament + React + PostgreSQL  
Hosting: Hetzner

## Goal

Launch a **minimal marketplace for selling advertising space on cars**, starting with the **Carguru car sharing fleet**.

Main value for advertisers:

**Visualization of vehicle movement and parking via telemetry heatmaps.**

Advertisers can evaluate **where ads were actually exposed in the city**.

The MVP focuses on:

- car inventory
- campaign management
- telemetry visualization
- advertiser reporting

---

# Documentation Structure

/docs

00_documentation_index.md

/product
01_mvp_product_overview.md
02_roles_and_permissions.md
03_mvp_scope.md

/architecture
04_system_architecture.md
05_telemetry_pipeline.md

/domain
06_domain_entities.md
07_entity_relationships.md

/admin
08_admin_panel.md

/portals
09_mediaowner_portal.md
10_advertiser_portal.md

/api
11_public_api.md

/deploy
12_vps_production.md — продакшен на VPS (Nginx, PHP-FPM, PostgreSQL, очередь, cron, ClickHouse, **GitHub Actions деплой**)
13_github_push.md — первый push в репозиторий [vladr1050/carfluencer](https://github.com/vladr1050/carfluencer)
14_docker.md — Docker Compose: Nginx, PHP-FPM, Postgres, queue, scheduler
15_github_actions.md — GitHub Actions: CI, деплой на VPS, секреты, environment production

Репозиторий также содержит: `deploy/nginx-carfluencer.conf.example`, `deploy/supervisor-laravel.conf.example`, `deploy/vps-first-deploy.sh.example`, `deploy/setup-ubuntu-server.sh` (авто-`.env` + стек на VPS), `deploy/post-pull.sh`, `.github/workflows/deploy-production.yml`.

## Local UI

- **`design/Files/`** — **MVP portals** (Figma UI + Laravel API), `npm run dev` → http://localhost:5174. See `design/Files/README.md` (dev: Vite proxy; prod: `VITE_API_URL`).
- **`frontend/`** — older minimal SPA (5173), optional if you use `design/Files` only.

/design
Visual_style_guide_CARfluencer.pdf — visual style guide