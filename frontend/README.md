# Carfluencer portals (production SPA)

UI/UX matches **`design/Files`** (Figma export): Tailwind v4, theme tokens, shadcn/Radix components, same layouts and pages. This app is wired to the **Laravel API** (`/api/*`).

## Run locally

1. **Backend** (`backend/`): `composer install`, `.env`, `php artisan migrate`, `php artisan serve` → `http://127.0.0.1:8000`

2. **Frontend**

   ```bash
   cd frontend
   npm install
   ```

   Optional `frontend/.env` — leave **`VITE_API_URL` unset** during dev so the Vite proxy forwards `/api` and `/storage` to Laravel (avoids CORS with Safari).

   ```bash
   npm run dev
   ```

   Open **http://localhost:5173** — `/` is the **login** screen (design parity). **Public notices:** `/welcome`.

3. **Production build**

   ```bash
   Для **www.carplace.lv** (SPA и `/api` на одном хосте): **`VITE_API_URL` не задавайте**. Иначе: `VITE_API_URL=https://www.carplace.lv npm run build`
   ```

   Or: `API_URL=... bash ../deploy/frontend-build-production.sh`

## Routes (React Router)

| Path | Screen |
|------|--------|
| `/`, `/login` | Login (Advertiser / Media owner tabs) |
| `/register` | Register |
| `/welcome` | CMS blocks from `GET /api/content-blocks` |
| `/media-owner/*` | Media owner portal |
| `/advertiser/*` | Advertiser portal |

Filament admin stays on Laravel: **`/admin`**.

## Stack

- Vite 6, React 18, **Tailwind CSS v4** (`@tailwindcss/vite`), **next-themes**
- Radix + shadcn-style UI under `src/app/components/ui/`
- Source layout mirrors `design/Files/src/app/` (pages, layouts)
