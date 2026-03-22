# Продакшен: www.carplace.lv

Краткий чеклист, как всё «сидит» на домене **https://www.carplace.lv** (SPA + Laravel + Filament на одном хосте).

## 1. DNS

- Запись **`A`** (и при необходимости **`AAAA`**) для **`www.carplace.lv`** → IP VPS.
- Для **`carplace.lv`** (apex) — тот же IP или редирект на `www` (удобно через Nginx).

## 2. Автоматически на сервере (рекомендуется)

Из **корня git-клона** на VPS (где лежат `backend/` и `deploy/`):

```bash
cd /var/www/carfluencer   # ваш DEPLOY_PATH
git pull
sudo bash deploy/apply-carplace-lv-on-server.sh
```

Скрипт **`deploy/apply-carplace-lv-on-server.sh`** выставит в **`backend/.env`** URL/CORS/Sanctum/cookies под **carplace.lv**, скопирует Nginx с подстановкой реального пути к репозиторию, проверит конфиг и сделает **`php artisan config:cache`**.

### TLS (Certbot)

Если ещё нет HTTPS:

```bash
sudo certbot --nginx -d www.carplace.lv -d carplace.lv
```

## 3. Nginx вручную (если без скрипта)

Шаблон: **`deploy/nginx-carfluencer.conf.example`** (`server_name www.carplace.lv carplace.lv`). Если каталог на сервере не **`/var/www/carfluencer`**, замените этот путь в файле на ваш **`DEPLOY_PATH`** (скрипт **`apply-carplace-lv-on-server.sh`** делает это сам).

## 4. Laravel `.env` вручную

Если скрипт не используете — скопируйте **`backend/.env.production.example`** → **`backend/.env`** или проверьте:

| Переменная | Значение |
|------------|----------|
| `APP_URL` | `https://www.carplace.lv` |
| `FRONTEND_URL` | `https://www.carplace.lv` |
| `CORS_ALLOWED_ORIGINS` | `https://www.carplace.lv,https://carplace.lv` |
| `SANCTUM_STATEFUL_DOMAINS` | `www.carplace.lv,carplace.lv` |
| `SESSION_DOMAIN` | `.carplace.lv` |

```bash
cd /var/www/carfluencer/backend
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

## 5. Сборка фронта

Если **`/api`** на **том же** origin, что и SPA — **`VITE_API_URL` не задаёте** (относительные запросы `/api`).

- Локально: `cd frontend && npm ci && npm run build`
- GitHub Actions: секрет **`FRONTEND_VITE_API_URL`** **не создавайте** (или оставьте пустым), если сайт открывается как `https://www.carplace.lv`.

Подробнее: **`docs/DEPLOY/15_github_actions.md`**.

## 6. Проверка

- Открыть **`https://www.carplace.lv`** — логин SPA.
- **`https://www.carplace.lv/admin`** — Filament.
- **`https://www.carplace.lv/api/...`** — ответы JSON (с авторизацией).

Общий гайд по VPS: **`docs/DEPLOY/12_vps_production.md`**.
