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

На VPS после **`deploy/setup-ubuntu-server.sh`** cron в **`/etc/cron.d/carfluencer-laravel`** должен вызывать **`schedule:run` достаточно часто** для интервала из админки (Telemetry → automation). В **`bootstrap/app.php`** задача **`telemetry:scheduler-tick`** стоит на **`everyMinute()`** — при **`0 * * * *`** cron инкрементальный pull фактически выполняется **раз в час**, независимо от «10 минут» в UI.

Рекомендуемый cron (раз в минуту):

```cron
* * * * * www-data cd /var/www/carfluencer/backend && /usr/bin/php8.4 artisan schedule:run >> /dev/null 2>&1
```

Шаблон: **`deploy/cron-carfluencer.example`**. Старые серверы с **`0 * * * *`** замените на **`* * * * *`**, иначе фоновая инкрементальная подгрузка ClickHouse почти не крутится.

## Queues

Async jobs (ClickHouse sync и др.): **`deploy/supervisor-laravel.conf.example`** → программа **`carfluencer-queue`** (`queue:work database`, пользователь `www-data`). Индекс артефактов: **`deploy/README.md`**.

Исторический sync из ClickHouse (`SyncTelemetryScopeFromClickHouseJob`) может работать до **7200s**: воркер обязан иметь **`--timeout=7200`** (иначе дефолт **60s** обрывает job и после нескольких попыток — `MaxAttemptsExceededException`). В **`config/queue.php`** для `database`/`redis` **`retry_after`** по умолчанию **7500**; при переопределении в **`.env`** держите значение **выше** времени самого долгого job.

Исторический импорт делает много HTTP-запросов к ClickHouse: таймаут одного запроса — **`TELEMETRY_CH_HTTP_TIMEOUT`** в **`.env`** (в конфиге по умолчанию **900** с, максимум **3600**). Слишком низкое значение даёт обрыв посреди страницы и повторы job до `MaxAttemptsExceededException`.

Ответы **JSONEachRow** читаются **потоково** (без удержания всего тела в памяти). При очень тяжёлых окнах задайте **`TELEMETRY_CH_QUEUE_MEMORY_LIMIT=1024M`** (или выше) и в Supervisor оставьте **`php -d memory_limit=1024M`** для воркера. Если в логах всё ещё **`memory exhausted`** в `ClickHouseHttpClient`, уменьшите **`TELEMETRY_CH_HISTORICAL_ROWS_PER_CHUNK`** или **`TELEMETRY_CH_STREAM_BATCH`**.

**Heatmap в браузере:** ошибки вида **`Allowed memory size of 134217728 bytes`** — это лимит **PHP-FPM** (**128M**). Поднимите **`memory_limit`** в пуле FPM (например **256M** или **512M**) для `php8.4-fpm`.

**PDF / Browsershot:** не используйте **`/snap/bin/chromium`** под **`www-data`** (snap требует свой home и cgroup). Поставьте Chromium из пакета (`.deb`) и в **`.env`**: **`CAMPAIGN_REPORT_CHROME_PATH=/usr/bin/chromium`** (или путь из `which chromium`).

После деплоя **`deploy/post-pull.sh`** вызывает **`queue:restart`** — воркеры завершают текущий job и Supervisor поднимает новый процесс.

## Storage

User uploads (vehicle images, campaign proofs) use `storage/app/public`. Ensure `php artisan storage:link` and that `public/storage` is writable by the web user.

## CORS / SPA

For a separate React origin, configure `config/cors.php` (publish if needed) and Sanctum `SANCTUM_STATEFUL_DOMAINS` if using cookie-based SPA auth. For pure Bearer tokens from the React app, CORS `allowed_origins` must include the frontend URL.

## SSL

Terminate TLS at Nginx with Let’s Encrypt (`certbot`).

## GitHub

Push this repository to GitHub; deploy via SSH pull, GitHub Actions, or your CI of choice. Keep `.env` out of version control.
