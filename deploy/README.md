# Деплой Carfluencer (прод)

| Файл | Назначение |
|------|------------|
| **`setup-ubuntu-server.sh`** | Первичная настройка VPS (PHP 8.4, Postgres, Nginx, `.env`, Composer, миграции, **Supervisor**, **cron**). Запуск: `sudo bash deploy/setup-ubuntu-server.sh 'https://домен'` |
| **`post-pull.sh`** | После `git pull`: зависимости, миграции, **`php artisan telemetry:ensure-env`** (добавляет в `.env` только отсутствующие ключи из **`telemetry.env.fragment`**), кэши, `queue:restart`, при `TELEMETRY_CLICKHOUSE_ENABLED=true` — **`telemetry:test-clickhouse`**. |
| **`telemetry.env.fragment`** | Шаблон переменных телеметрии/ClickHouse для автослияния в `backend/.env` (не перезаписывает существующие ключи). |
| **`nginx-carfluencer.conf.example`** | Виртуальный хост Nginx → `backend/public`, PHP-FPM 8.4. |
| **`supervisor-laravel.conf.example`** | Шаблон воркера очереди (`queue:work database`). Копируется скриптом в `/etc/supervisor/conf.d/`. |
| **`cron-carfluencer.example`** | Шаблон cron для `schedule:run`. На чистой установке создаётся скриптом автоматически. |
| **`vps-first-deploy.sh.example`** | Ручной «только Laravel» без полного стека (если уже есть Nginx/PHP/БД). |
| **`frontend-build-production.sh`** | Сборка **`frontend/dist`** с **`API_URL=...`** для выкладки на VPS. |
| **`seed-production-on-server.sh.example`** | Один раз на VPS: **`php artisan db:seed --force`** от **`www-data`** (демо-данные + админ `admin@carfluencer.test` / `password`). |

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
