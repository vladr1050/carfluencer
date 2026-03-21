# Деплой Carfluencer (прод)

| Файл | Назначение |
|------|------------|
| **`setup-ubuntu-server.sh`** | Первичная настройка VPS (PHP 8.4, Postgres, Nginx, `.env`, Composer, миграции, **Supervisor**, **cron**). Запуск: `sudo bash deploy/setup-ubuntu-server.sh 'https://домен'` |
| **`post-pull.sh`** | После `git pull` на сервере: зависимости, миграции, кэши, `queue:restart`. Вызывается из **GitHub Actions** (`.github/workflows/deploy-production.yml`). |
| **`nginx-carfluencer.conf.example`** | Виртуальный хост Nginx → `backend/public`, PHP-FPM 8.4. |
| **`supervisor-laravel.conf.example`** | Шаблон воркера очереди (`queue:work database`). Копируется скриптом в `/etc/supervisor/conf.d/`. |
| **`cron-carfluencer.example`** | Шаблон cron для `schedule:run`. На чистой установке создаётся скриптом автоматически. |
| **`vps-first-deploy.sh.example`** | Ручной «только Laravel» без полного стека (если уже есть Nginx/PHP/БД). |
| **`frontend-build-production.sh`** | Сборка **`frontend/dist`** с **`API_URL=...`** для выкладки на VPS. |

Полная инструкция: **`docs/DEPLOY/12_vps_production.md`**, CI/CD: **`docs/DEPLOY/15_github_actions.md`**.

### Первый админ Filament

После миграций на сервере:

```bash
cd /var/www/carfluencer/backend
sudo -u www-data php artisan make:filament-user
```

(или от `root`, если `.env` читается.)

### Если Supervisor не стартует (`status=2`, нет `/var/run/supervisor.sock`)

Любой файл в `/etc/supervisor/conf.d/*.conf` с **несуществующим** `stdout_logfile` / путём к `command` ломает весь supervisord. Отключите проблемный конфиг (`mv …conf …conf.disabled`) и `systemctl restart supervisor`.
