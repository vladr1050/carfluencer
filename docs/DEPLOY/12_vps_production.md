# Развёртывание на VPS (пример: 135.181.36.104)

Автоматически поднять сервер из этого репозитория **нельзя** без SSH-доступа с вашей машины. Ниже — что сделать **на сервере** после `ssh root@135.181.36.104` (или пользователь с sudo).

Предположения: **Ubuntu 22.04/24.04**, домен или доступ по IP, **PostgreSQL** (не SQLite в проде).

---

## 1. Базовые пакеты

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git nginx php8.3-fpm php8.3-cli php8.3-pgsql php8.3-xml php8.3-mbstring \
  php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl php8.3-gd unzip curl
sudo apt install -y postgresql postgresql-contrib
sudo apt install -y supervisor
```

Composer (если нет):

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

---

## 2. Пользователь и каталог приложения

```bash
sudo adduser --disabled-password --gecos "" evoapp
sudo mkdir -p /var/www/carfluencer
sudo chown evoapp:evoapp /var/www/carfluencer
```

Деплой кода (один из вариантов):

```bash
sudo -u evoapp -H bash -c 'cd /var/www/carfluencer && git clone <YOUR_REPO_URL> .'
# или rsync/scp с локальной машины в /var/www/carfluencer/backend
```

Рабочая директория Laravel: **`/var/www/carfluencer/backend`** (если в репозитории корень = монорепо).

---

## 3. PostgreSQL

```bash
sudo -u postgres psql -c "CREATE USER evo WITH PASSWORD 'СИЛЬНЫЙ_ПАРОЛЬ';"
sudo -u postgres psql -c "CREATE DATABASE evo OWNER evo;"
```

В `backend/.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=evo
DB_USERNAME=evo
DB_PASSWORD=...
```

---

## 4. Laravel `.env` на проде

Скопируйте `backend/.env.example` → `backend/.env`, затем обязательно:

| Переменная | Значение |
|------------|----------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `http://135.181.36.104` или `https://ваш-домен` |
| `APP_KEY` | `php artisan key:generate` |
| `DB_*` | см. выше |
| `QUEUE_CONNECTION` | `database` (и таблицы jobs из миграций) или `redis` |
| `SESSION_DRIVER` | `database` или `redis` |
| `CACHE_STORE` | `database` или `redis` |

**ClickHouse (живые точки):**

```env
TELEMETRY_CLICKHOUSE_ENABLED=true
TELEMETRY_CLICKHOUSE_URL=http://ВАШ_CH:8123
TELEMETRY_CLICKHOUSE_DATABASE=default
TELEMETRY_CLICKHOUSE_USER=...
TELEMETRY_CLICKHOUSE_PASSWORD=...
TELEMETRY_CLICKHOUSE_LOCATIONS_TABLE=location
TELEMETRY_CH_SCHEMA_PRESET=location
TELEMETRY_CH_TIMESTAMP_TYPE=unix_seconds
TELEMETRY_CH_DEVICE_ID_COLUMN=imei
TELEMETRY_CH_SPEED_COLUMN=gpsSpeed
TELEMETRY_HEATMAP_DRIVER=database
```

**CORS для SPA** (если фронт на другом origin):

```env
CORS_ALLOWED_ORIGINS=https://ваш-фронт,http://135.181.36.104:5174
```

Команды:

```bash
cd /var/www/carfluencer/backend
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize
```

Права:

```bash
sudo chown -R evoapp:www-data /var/www/carfluencer/backend/storage /var/www/carfluencer/backend/bootstrap/cache
sudo chmod -R ug+rwx /var/www/carfluencer/backend/storage /var/www/carfluencer/backend/bootstrap/cache
```

---

## 5. Nginx

Пример конфига: репозиторий `deploy/nginx-carfluencer.conf.example`.

```bash
sudo cp deploy/nginx-carfluencer.conf.example /etc/nginx/sites-available/carfluencer
# отредактируйте root, server_name, php8.3-fpm.sock
sudo ln -s /etc/nginx/sites-available/carfluencer /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

---

## 6. Очередь и планировщик (обязательно для синка ClickHouse)

**Cron** (пользователь `evoapp`):

```cron
* * * * * cd /var/www/carfluencer/backend && php artisan schedule:run >> /dev/null 2>&1
```

Убедитесь, что в `routes/console.php` или `bootstrap/app.php` зарегистрирован вызов `telemetry:scheduler-tick` **каждую минуту** (как у вас в проекте).

**Supervisor** — worker очереди: пример `deploy/supervisor-laravel.conf.example`.

```bash
sudo cp deploy/supervisor-laravel.conf.example /etc/supervisor/conf.d/laravel-worker.conf
# поправьте пути и пользователя
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start laravel-worker:*
```

Проверка синка: в админке **Telematics → ClickHouse**, либо `php artisan telemetry:test-clickhouse`.

---

## 7. TLS (рекомендуется)

С доменом:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d ваш-домен
```

Обновите `APP_URL` на `https://...`.

---

## 8. Фронт (design/Files)

Сборка на сервере или CI, выкладка статики в `/var/www/carfluencer/design-dist` + отдельный `server {}` в Nginx, либо раздача с того же хоста. В `.env` фронта задайте `VITE_API_URL` на URL API (`https://ваш-домен` или `http://135.181.36.104`).

---

## 9. Файрвол

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

---

## 10. GitHub Actions — автодеплой по SSH

В репозитории: **`.github/workflows/deploy-production.yml`** и скрипт **`deploy/post-pull.sh`**.

### Два ключа (не путать)

| Назначение | Где приватный | Где публичный |
|------------|----------------|---------------|
| **GitHub Actions → ваш VPS** | Секрет `DEPLOY_SSH_PRIVATE_KEY` в GitHub | `~/.ssh/authorized_keys` пользователя деплоя на сервере |
| **VPS → GitHub** (`git fetch`) | На сервере (`~/.ssh/id_ed25519` или отдельный deploy key) | **Deploy keys** репозитория (только read) или SSH ключа вашего пользователя |

### Секреты в GitHub

**Settings → Secrets and variables → Actions → New repository secret:**

| Имя | Содержимое |
|-----|------------|
| `DEPLOY_HOST` | IP или домен (нестандартный порт: `host:2222`) |
| `DEPLOY_USER` | Пользователь SSH (например `evoapp`) |
| `DEPLOY_SSH_PRIVATE_KEY` | Весь файл приватного ключа (от `BEGIN` до `END`) |
| `DEPLOY_PATH` | Каталог **корня git** на сервере, например `/var/www/carfluencer` (рядом должны быть `backend/` и `deploy/`) |

Запуск:

- вручную: **Actions → Deploy production → Run workflow**
- автоматически: **push в `main` или `master`** при изменениях в `backend/**`, `deploy/post-pull.sh` или самом workflow

На сервере перед первым деплоем: `git clone` репозитория в `DEPLOY_PATH`, один раз выполнить настройку из разделов 3–6 (`.env`, nginx, supervisor, cron).

Опционально усилить безопасность: в workflow добавить параметр `fingerprint` для `appleboy/ssh-action` (SHA256 хост-ключа сервера), см. [документацию action](https://github.com/appleboy/ssh-action).

---

## Чеклист «всё поднято»

- [ ] Открывается `/` или `/admin` без 502  
- [ ] `php artisan migrate --force` без ошибок  
- [ ] `supervisorctl status` — worker `RUNNING`  
- [ ] Cron от `evoapp` каждую минуту  
- [ ] `telemetry:test-clickhouse` OK  
- [ ] После синка в админке Heatmap показывает точки из `device_locations`  

Если нужен **один скрипт** с вашими путями — скопируйте и отредактируйте `deploy/vps-first-deploy.sh.example`.
