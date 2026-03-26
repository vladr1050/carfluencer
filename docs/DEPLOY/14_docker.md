# Docker: весь стек (Nginx + PHP-FPM + PostgreSQL + очередь + планировщик)

В корне репозитория **`docker-compose.yml`** поднимает:

| Сервис | Роль |
|--------|------|
| `postgres` | PostgreSQL 16 |
| `app` | PHP 8.4-FPM (Laravel) |
| `nginx` | Веб-сервер, раздаёт `backend/public` |
| `queue` | `php artisan queue:work` |
| `scheduler` | `php artisan schedule:work` (в т.ч. телеметрия по расписанию) |

ClickHouse **в compose не входит** — остаётся внешний URL в `backend/.env` (`TELEMETRY_CLICKHOUSE_*`).

Образ **`docker/php/Dockerfile`** уже содержит **Node.js 22**, **Chromium** и зависимости для **Browsershot** (PDF/heatmap отчётов). В `docker-compose.yml` для `app` / `queue` / `scheduler` заданы `CAMPAIGN_REPORT_CHROME_PATH=/usr/bin/chromium`. После смены Dockerfile выполните **`docker compose build --no-cache`** и перезапустите контейнеры.

---

## Локально (Mac / Linux)

```bash
cd /path/to/Evo_ad_x_crflncr

cp backend/.env.example backend/.env
```

В **`backend/.env`** для Docker задайте БД:

```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=evo
DB_USERNAME=evo
DB_PASSWORD=evo
```

Дальше:

```bash
docker compose build
docker compose run --rm app composer install --no-interaction
docker compose run --rm app php artisan key:generate --no-interaction
docker compose run --rm app php artisan migrate --force --no-interaction
docker compose up -d
```

Открыть: **http://localhost:8080** (админка Filament: **`/admin`**).

Остановка: `docker compose down` (данные Postgres в volume `evo_pg_data` сохраняются).

---

## VPS только с Docker (без ручного Nginx/PHP на хосте)

1. Установите [Docker Engine](https://docs.docker.com/engine/install/ubuntu/) и плагин Compose.
2. Клонируйте репозиторий, например в `/var/www/carfluencer`.
3. Настройте **`backend/.env`** (production: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, секреты БД — см. ниже про Postgres в compose).
4. Для **порта 80** вместо 8080 отредактируйте `docker-compose.yml` у сервиса `nginx`:

   ```yaml
   ports:
     - '80:80'
   ```

   Или используйте отдельный override-файл (не коммитьте секреты).

5. Первый запуск — те же команды, что в разделе «Локально» (с хоста, из корня репо):

   ```bash
   docker compose build
   docker compose run --rm app composer install --no-dev --no-interaction --optimize-autoloader
   docker compose run --rm app php artisan key:generate --no-interaction
   docker compose run --rm app php artisan migrate --force --no-interaction
   docker compose up -d
   ```

6. TLS: перед контейнером поставьте **Caddy / Traefik / host nginx** как reverse proxy с Let’s Encrypt, или пробросьте 443 на отдельный контейнер — в базовом compose только HTTP.

**Важно:** пароль Postgres в compose сейчас **`evo`** — на проде замените через переменные окружения в `docker-compose.yml` (например `POSTGRES_PASSWORD_FILE` или env из `.env` через `env_file`) и синхронно обновите `DB_PASSWORD` в `backend/.env`.

---

## Обновление кода

После `git pull`:

```bash
docker compose build
docker compose run --rm app composer install --no-dev --no-interaction --optimize-autoloader
docker compose run --rm app php artisan migrate --force --no-interaction
docker compose run --rm app php artisan config:cache
docker compose run --rm app php artisan route:cache
docker compose run --rm app php artisan view:cache
docker compose run --rm app php artisan filament:optimize || true
docker compose up -d
docker compose exec app php artisan queue:restart || true
```

Это по смыслу совпадает с **`deploy/post-pull.sh`** (его можно вызывать на хосте, если `composer`/`php` установлены; в чистом Docker удобнее команды выше).

---

## Только PostgreSQL (как раньше)

Если нужен один контейнер БД для локального Laravel на хосте:

```bash
docker compose up -d postgres
```

В `backend/.env`: `DB_HOST=127.0.0.1`, порт `5432`.

---

## Связь с GitHub Actions

Текущий workflow **Deploy production** рассчитан на **SSH + git на VPS**. Если деплой полностью через Docker, на сервере вместо набора команд artisan можно вызывать скрипт из раздела «Обновление кода» или расширить workflow отдельной job (по желанию).
