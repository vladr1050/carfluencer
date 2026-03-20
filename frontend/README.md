# Carfluencer portals (production SPA)

This app talks to the **Laravel API**. It is **not** the Figma prototype in `../design/Files`.

## Run locally

1. **Backend** (from repo root or `backend/`):

   ```bash
   cd backend
   composer install
   cp .env.example .env && php artisan key:generate
   php artisan migrate
   php artisan serve
   ```

   API: `http://127.0.0.1:8000`

2. **Frontend**:

   ```bash
   cd frontend
   npm install
   ```

   Create `frontend/.env` if needed:

   ```env
   VITE_API_URL=http://127.0.0.1:8000
   ```

   ```bash
   npm run dev
   ```

   Open **http://localhost:5173**

3. **Hard refresh** if styles look old: `Cmd+Shift+R` (Mac) / `Ctrl+Shift+R` (Windows).

4. **Dark / light theme**: toggle in the portal sidebar (logged in) or on the home header (moon icon).

## Figma / design prototype

Static UI from Figma lives in **`design/Files`** (separate Vite app, port **5174** after `npm i && npm run dev` there). That bundle is **not** wired to your Laravel API by default.
