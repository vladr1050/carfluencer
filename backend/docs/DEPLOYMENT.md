# Deployment notes (Hetzner & production)

## Server assumptions

- Ubuntu LTS VM on Hetzner Cloud
- Nginx (or Caddy) as reverse proxy
- PHP 8.4 + PHP-FPM
- PostgreSQL
- Node.js 20+ (for building Filament assets if needed; pre-built assets live in `public/`)

## Environment variables

Copy `backend/.env.example` to `.env` on the server and set at minimum:

| Variable | Purpose |
|----------|---------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | Public HTTPS URL |
| `DB_*` | PostgreSQL connection |
| `SESSION_DRIVER` | `database` or `redis` |
| `QUEUE_CONNECTION` | `database` or `redis` (recommended for jobs) |
| `CACHE_STORE` | `redis` or `database` |

Generate key: `php artisan key:generate`

## Build & migrate

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Scheduler

На VPS после **`deploy/setup-ubuntu-server.sh`** cron уже в **`/etc/cron.d/carfluencer-laravel`** (`www-data`, `php8.4 artisan schedule:run`). Вручную:

```cron
0 * * * * www-data cd /var/www/carfluencer/backend && /usr/bin/php8.4 artisan schedule:run >> /dev/null 2>&1
```

Инкрементальный опрос ClickHouse в тике не чаще одного раза в час при таком cron; для интервала из админки меньше 60 минут используйте `* * * * *` и `everyMinute()` в `bootstrap/app.php`.

## Queues

Async jobs (ClickHouse sync и др.): **`deploy/supervisor-laravel.conf.example`** → программа **`carfluencer-queue`** (`queue:work database`, пользователь `www-data`). Индекс артефактов: **`deploy/README.md`**.

После деплоя **`deploy/post-pull.sh`** вызывает **`queue:restart`** — воркеры завершают текущий job и Supervisor поднимает новый процесс.

## Storage

User uploads (vehicle images, campaign proofs) use `storage/app/public`. Ensure `php artisan storage:link` and that `public/storage` is writable by the web user.

## CORS / SPA

For a separate React origin, configure `config/cors.php` (publish if needed) and Sanctum `SANCTUM_STATEFUL_DOMAINS` if using cookie-based SPA auth. For pure Bearer tokens from the React app, CORS `allowed_origins` must include the frontend URL.

## SSL

Terminate TLS at Nginx with Let’s Encrypt (`certbot`).

## GitHub

Push this repository to GitHub; deploy via SSH pull, GitHub Actions, or your CI of choice. Keep `.env` out of version control.
