# Carfluencer V2 — MVP SPA (`design/Files`)

Figma/Make export, **wired to the Laravel API** — this is the **primary MVP web UI** (port **5174**).

Figma reference: https://www.figma.com/design/fGnCxsaJwAQlt96RYpryvA/Carfluencer-V2

## Run locally

```bash
cd backend && php artisan serve
# other terminal:
cd design/Files
npm i
cp .env.example .env   # dev: leave VITE_API_URL unset (Vite → Laravel proxy, Safari-safe)
npm run dev
```

Open **http://localhost:5174**

- **Register:** http://localhost:5174/register  
- **Login:** http://localhost:5174/login  

After `php artisan migrate --seed`: `advertiser@brand.test` / `password`, `media@carguru.test` / `password`.

## MVP feature coverage

| Area | Status |
|------|--------|
| Auth login / register | ✅ |
| Advertiser: dashboard, vehicles, campaigns, campaign detail (**attach / remove / proof**), heatmap, pricing, discounts | ✅ |
| Media owner: dashboard, vehicles (+ add, image), campaigns, campaign detail (**proof upload**), earnings | ✅ |

## `frontend/` (5173)

Older minimal SPA — optional; **not required** if you standardise on this app.

## Production build

Set **`VITE_API_URL`** to your public API origin before `npm run build` (Vite proxy is dev-only).

Images: `php artisan storage:link` on the server.
