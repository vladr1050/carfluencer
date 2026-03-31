# Деплой Carfluencer (прод)

| Файл | Назначение |
|------|------------|
| **`setup-ubuntu-server.sh`** | Первичная настройка VPS (PHP 8.4 + **FFI**, **libh3** для Impression Engine, Postgres, Nginx, `.env`, Composer, миграции, **Supervisor**, **cron**, **Node 22 + Chromium** для PDF-отчётов Browsershot). Запуск: `sudo bash deploy/setup-ubuntu-server.sh 'https://www.carplace.lv'` |
| **`install-campaign-report-browsershot-on-ubuntu.sh`** | На уже развёрнутом Ubuntu: Node + Chromium и подсказка для `CAMPAIGN_REPORT_*` в `.env`. `sudo bash deploy/install-campaign-report-browsershot-on-ubuntu.sh` |
| **`npm-install-backend-www-data.sh`** | `npm install` в `backend/` от root, затем `chown` `node_modules` → **www-data** (puppeteer). |
| **`install-google-chrome-for-browsershot.sh`** | **Google Chrome .deb** для отчётов: **snap Chromium под Supervisor не работает** — `sudo bash deploy/install-google-chrome-for-browsershot.sh`, в `.env`: `CAMPAIGN_REPORT_CHROME_PATH=/usr/bin/google-chrome-stable`. |
| **`apply-php-memory-for-heatmap.sh`** | Поднять `memory_limit` в **php-fpm + cli** и добавить `-d memory_limit=512M` в **Supervisor** для `carfluencer-queue`. `cd /var/www/carfluencer && sudo bash deploy/apply-php-memory-for-heatmap.sh` |
| **`apply-carplace-lv-on-server.sh`** | На уже развёрнутом VPS: **.env** под **www.carplace.lv**, Nginx из примера с подстановкой пути, `config:cache`. `sudo bash deploy/apply-carplace-lv-on-server.sh` из корня клона. См. **`docs/DEPLOY/16_carplace_lv.md`**. |
| **`post-pull.sh`** | После `git pull`: зависимости, миграции, **`php artisan telemetry:ensure-env`** (добавляет в `.env` только отсутствующие ключи из **`telemetry.env.fragment`** и **`impression_engine.env.fragment`**), кэши, `queue:restart`, при `TELEMETRY_CLICKHOUSE_ENABLED=true` — **`telemetry:test-clickhouse`**. |
| **`telemetry.env.fragment`** | Шаблон переменных телеметрии/ClickHouse для автослияния в `backend/.env` (не перезаписывает существующие ключи). |
| **`impression_engine.env.fragment`** | Impression Engine: **`IMPRESSION_ENGINE_H3_DRIVER=real`** и опции H3; сливается тем же **`php artisan telemetry:ensure-env`**, что и telemetry-фрагмент. |
| **`install-h3-v3-from-source-ubuntu.sh`** | Если **`libh3-dev`** нет в apt или дистрибутив отдаёт **H3 v4** (несовместим с `php-h3`), собрать **Uber H3 v3.7.2** в `/usr/local` (как в **`docker/php/Dockerfile`**). |
| **`nginx-carfluencer.conf.example`** | Виртуальный хост Nginx → `backend/public`, PHP-FPM 8.4. |
| **`supervisor-laravel.conf.example`** | Шаблон воркера очереди (`queue:work database`). Копируется скриптом в `/etc/supervisor/conf.d/`. |
| **`cron-carfluencer.example`** | Шаблон cron для `schedule:run`. На чистой установке создаётся скриптом автоматически. |
| **`vps-first-deploy.sh.example`** | Ручной «только Laravel» без полного стека (если уже есть Nginx/PHP/БД). |
| **`frontend-build-production.sh`** | Сборка **`frontend/dist`**. Для same-origin с **www.carplace.lv** **`API_URL` не задавайте**. |
| **`seed-production-on-server.sh.example`** | Один раз на VPS: **`php artisan db:seed --force`** от **`www-data`** (демо-данные + админ `admin@carfluencer.test` / `password`). |
| **`restore-postgres-snapshot.sh.example`** | Восстановление **`pg_dump -Fc`** на сервере (после **`php artisan db:export-snapshot`** локально при PostgreSQL). См. **`docs/OPERATIONS/03_full_database_sync.md`**. |

Полная инструкция: **`docs/DEPLOY/12_vps_production.md`**, CI/CD: **`docs/DEPLOY/15_github_actions.md`**, логи Laravel: **`docs/OPERATIONS/01_logging.md`**.

### Первый админ Filament

**Вариант A — демо-сидер** (даёт админа `admin@carfluencer.test` / `password`, см. **`seed-production-on-server.sh.example`**).

**Вариант B — вручную** после миграций:

```bash
cd /var/www/carfluencer/backend
sudo -u www-data php artisan make:filament-user
```

У `make:filament-user` роль по умолчанию может быть не `admin` — для панели нужно **`role=admin`** и **`status=active`** в БД.

### Если Supervisor не стартует (`status=2`, нет `/var/run/supervisor.sock`)

Любой файл в `/etc/supervisor/conf.d/*.conf` с **несуществующим** `stdout_logfile` / путём к `command` ломает весь supervisord. Отключите проблемный конфиг (`mv …conf …conf.disabled`) и `systemctl restart supervisor`.
