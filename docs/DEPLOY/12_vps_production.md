# Развёртывание на VPS

Продакшен-домен: **https://www.carplace.lv** — см. **`docs/DEPLOY/16_carplace_lv.md`**.

Автоматически поднять сервер из этого репозитория **нельзя** без SSH-доступа с вашей машины. Ниже — что сделать **на сервере** после `ssh root@ВАШ_IP_ИЛИ_ДОМЕН` (или пользователь с sudo).

Предположения: **Ubuntu 22.04/24.04**, домен или доступ по IP, **PostgreSQL** (не SQLite в проде).

---

## Быстрый старт (один скрипт)

Если репозиторий уже в **`/var/www/carfluencer`** (скрипт подключает **PPA ondrej/php** и ставит **PHP 8.4** — нужен для текущего `composer.lock` / Symfony 8):

```bash
cd /var/www/carfluencer
sudo bash deploy/setup-ubuntu-server.sh 'https://www.carplace.lv'
```

Скрипт ставит **Nginx, PostgreSQL, PHP 8.4-FPM, Composer, Supervisor**, собирает **`backend/.env`**, **`composer install`**, **`migrate`**, кэши, включает сайт, пишет **cron** `schedule:run` в **`/etc/cron.d/carfluencer-laravel`**, поднимает воркер **`carfluencer-queue`** в Supervisor. Пароль БД выводится в конце — **сохрани**. Остаётся: **первый админ** (`make:filament-user`), при необходимости **TLS** (§7) и **ClickHouse** в `.env`.

Если Nginx не стартует с ошибкой **Address already in use** на порту 80 — часто уже запущен **Apache** (`systemctl stop apache2 && systemctl disable apache2`).

---

## 1. Базовые пакеты

```bash
sudo apt update && sudo apt upgrade -y
sudo add-apt-repository -y ppa:ondrej/php && sudo apt update
sudo apt install -y git nginx php8.4-fpm php8.4-cli php8.4-pgsql php8.4-xml php8.4-mbstring \
  php8.4-curl php8.4-zip php8.4-bcmath php8.4-intl php8.4-gd unzip curl
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
sudo adduser --disabled-password --gecos "" evoad
sudo mkdir -p /var/www/carfluencer
sudo chown evoad:evoad /var/www/carfluencer
```

Деплой кода (один из вариантов):

```bash
sudo -u evoad -H bash -c 'cd /var/www/carfluencer && git clone <YOUR_REPO_URL> .'
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

Готовый шаблон для прода: **`backend/.env.production.example`** (уже с **www.carplace.lv**) — скопируйте в **`backend/.env`** и замените **`CHANGE_ME_DB_PASSWORD`**, при необходимости ClickHouse; для другого домена поправьте `APP_URL`, `FRONTEND_URL`, CORS, Sanctum, `SESSION_DOMAIN`:

```bash
cd /var/www/carfluencer/backend
cp .env.production.example .env
nano .env
```

Либо по-прежнему: `backend/.env.example` → `.env` и руками выставить `APP_ENV=production`, `APP_DEBUG=false`, `DB_CONNECTION=pgsql`, …

Далее обязательно:

| Переменная | Значение |
|------------|----------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://www.carplace.lv` (см. **`docs/DEPLOY/16_carplace_lv.md`**) |
| `APP_KEY` | `php artisan key:generate` |
| `DB_*` | см. выше |
| `QUEUE_CONNECTION` | `database` (и таблицы jobs из миграций) или `redis` |
| `SESSION_DRIVER` | `database` или `redis` |
| `CACHE_STORE` | `database` или `redis` |

**ClickHouse (живые точки):**

```env
TELEMETRY_CLICKHOUSE_ENABLED=true
TELEMETRY_CLICKHOUSE_URL=http://178.63.17.153:8123
TELEMETRY_CLICKHOUSE_DATABASE=default
TELEMETRY_CLICKHOUSE_USER=...
TELEMETRY_CLICKHOUSE_PASSWORD=...
TELEMETRY_CLICKHOUSE_LOCATIONS_TABLE=location
TELEMETRY_CH_SCHEMA_PRESET=location
TELEMETRY_CH_TIMESTAMP_COLUMN=timestamp
TELEMETRY_CH_TIMESTAMP_TYPE=unix_seconds
TELEMETRY_CH_DEVICE_ID_COLUMN=imei
TELEMETRY_CH_SPEED_COLUMN=gpsSpeed
TELEMETRY_HEATMAP_DRIVER=database
# Лимиты нагрузки на внешний CH (см. config/telemetry.php и .env.production.example)
TELEMETRY_CH_INCREMENTAL_ROWS_PER_IMEI=15000
TELEMETRY_CH_PAUSE_MS_BETWEEN_IMEI=300
TELEMETRY_CH_MAX_IMEIS_PER_TICK=35
TELEMETRY_CH_GLOBAL_INCREMENTAL_ROWS=25000
TELEMETRY_CH_HTTP_TIMEOUT=120
```

**Если в админке «ClickHouse pull: Disabled» или джобы не качают данные:**

- Команда **`php artisan telemetry:ensure-env`** (уже в **`deploy/post-pull.sh`**) добавляет в **`backend/.env` только отсутствующие** ключи из **`deploy/telemetry.env.fragment`**. Уже записанные значения **не перезаписывает** — если у вас стоит **`TELEMETRY_CLICKHOUSE_ENABLED=false`**, поменяйте вручную на **`true`** (или скопируйте блок из **`backend/.env.production.example`**).
- После правок: **`php artisan config:cache`** (или **`config:clear`**) и **`php artisan queue:restart`**. Проверка: **`php artisan telemetry:test-clickhouse`**.

**CORS для SPA** (если фронт на другом origin):

```env
CORS_ALLOWED_ORIGINS=https://www.carplace.lv,https://carplace.lv
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
php artisan telemetry:test-clickhouse
```

Права:

```bash
sudo chown -R evoad:www-data /var/www/carfluencer/backend/storage /var/www/carfluencer/backend/bootstrap/cache
sudo chmod -R ug+rwx /var/www/carfluencer/backend/storage /var/www/carfluencer/backend/bootstrap/cache
```

### Полная копия локальной БД на прод

Пошагово (PostgreSQL / SQLite, `db:export-snapshot`, `pg_restore`, `pgloader`): **`docs/OPERATIONS/03_full_database_sync.md`**.

### Демо-сидер на проде (опционально)

`DatabaseSeeder` **не** очищает БД, но создаёт/обновляет демо-пользователей, политики размеров, настройки телеметрии, демо-кампанию и т.д. (см. `backend/database/seeders/DatabaseSeeder.php`).

**Скрипт из репозитория** (запуск из **корня** клона, удобно от `root`):

```bash
cd /var/www/carfluencer
cp deploy/seed-production-on-server.sh.example deploy/seed-production-on-server.sh
chmod +x deploy/seed-production-on-server.sh
./deploy/seed-production-on-server.sh
```

Вручную:

```bash
cd /var/www/carfluencer/backend
sudo -u www-data php artisan db:seed --force --no-interaction
```

После сида вход в админку: **`admin@carfluencer.test`** / **`password`** — **сразу смените пароль** (и демо-аккаунты `media@…` / `advertiser@…`).

---

## 5. Nginx

Пример конфига: репозиторий `deploy/nginx-carfluencer.conf.example`.

```bash
sudo cp deploy/nginx-carfluencer.conf.example /etc/nginx/sites-available/carfluencer
# отредактируйте root, server_name, php8.4-fpm.sock
sudo ln -s /etc/nginx/sites-available/carfluencer /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

---

## 6. Очередь и планировщик (синк ClickHouse / jobs)

После **`deploy/setup-ubuntu-server.sh`** уже настроены:

- **`/etc/cron.d/carfluencer-laravel`** — каждую минуту `php8.4 artisan schedule:run` от **`www-data`**
- **Supervisor** — программа **`carfluencer-queue`** (`queue:work database`), лог: `backend/storage/logs/worker.log`

Планировщик Laravel: в **`bootstrap/app.php`** зарегистрировано **`telemetry:scheduler-tick`** каждую минуту. Сам **инкрементальный** опрос ClickHouse срабатывает не чаще интервала из админки (**Telematics → ClickHouse & automation**, по умолчанию 10 минут) и обрабатывает ограниченное число IMEI за проход (`TELEMETRY_CH_MAX_IMEIS_PER_TICK`), чтобы не перегружать внешний сервер.

**Если ставили сервер вручную** (без скрипта):

```bash
sudo cp deploy/supervisor-laravel.conf.example /etc/supervisor/conf.d/carfluencer-queue.conf
sudo sed -i 's#/var/www/carfluencer#/ПУТЬ_К_КЛОНУ#g' /etc/supervisor/conf.d/carfluencer-queue.conf
sudo cp deploy/cron-carfluencer.example /etc/cron.d/carfluencer-laravel
# отредактируй пути и php в cron-файле при необходимости
sudo chmod 644 /etc/cron.d/carfluencer-laravel
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start 'carfluencer-queue:*'
```

Проверка: `supervisorctl status`, `sudo -u www-data -H bash -c 'cd /var/www/carfluencer/backend && php artisan schedule:list'`. Синк ClickHouse: админка **Telematics → ClickHouse** или `php artisan telemetry:test-clickhouse`.

---

## 7. TLS (рекомендуется)

С доменом:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d ваш-домен
```

Обновите `APP_URL` на `https://...`.

---

## 8. Фронт — React порталы (`frontend/`)

Прод-сборка с **тем же origin**, что у API в браузере (иначе CORS / cookies усложняются):

```bash
cd /path/to/carfluencer
API_URL=http://ВАШ_IP_ИЛИ_https://домен bash deploy/frontend-build-production.sh
```

Выкладка на сервер:

```bash
# с машины разработчика (пример)
tar -C frontend/dist -czf /tmp/fe.tgz .
scp /tmp/fe.tgz root@СЕРВЕР:/tmp/
ssh root@СЕРВЕР 'mkdir -p /var/www/carfluencer/frontend/dist && tar -xzf /tmp/fe.tgz -C /var/www/carfluencer/frontend/dist && systemctl reload nginx'
```

Nginx: **`deploy/nginx-carfluencer.conf.example`** — раздаёт **`/`**, **`/media-owner/*`**, **`/advertiser/*`** из **`frontend/dist`**, **`/api`**, **`/admin`**, **`/livewire`**, **`/filament`** — через Laravel.

После смены домена / HTTPS: пересобери фронт с новым **`API_URL`** и снова залей **`dist`**. В **`backend/.env`** обнови **`APP_URL`**, **`FRONTEND_URL`**, при необходимости **`CORS_ALLOWED_ORIGINS`**.

Прототип в **`design/Files/`** в прод не входит — только **`frontend/`**.  
GitHub **CI** собирает `frontend/` при каждом push; **в репозиторий `dist` не коммитится** — на VPS после `git pull` нужно либо заново выполнить **`deploy/frontend-build-production.sh`** и залить **`frontend/dist`**, либо включить на сервере **`CARFLUENCER_FRONTEND_BUILD=1`** (см. `deploy/post-pull.sh`, нужны **Node.js + npm**).

---

## 9. Файрвол

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
```

---

## 10. GitHub Actions — автодеплой по SSH

Полная инструкция по workflow, секретам и окружению **`production`**: **`docs/DEPLOY/15_github_actions.md`**.

Кратко: **`.github/workflows/deploy-production.yml`**, скрипт **`deploy/post-pull.sh`**. Деплой после **успешного CI** на push в `main`/`master` или вручную из вкладки Actions. **`post-pull.sh`** сам дополняет `backend/.env` телеметрией из **`deploy/telemetry.env.fragment`** (существующие переменные не меняются).

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
| `DEPLOY_USER` | Пользователь SSH: **`root`** или отдельный пользователь (**`evoad`** и т.д.). Ключ для Actions — в `authorized_keys` этого пользователя (`/root/.ssh/…` или `/home/evoad/.ssh/…`). |
| `DEPLOY_SSH_PRIVATE_KEY` | Весь файл приватного ключа (от `BEGIN` до `END`) |
| `DEPLOY_PATH` | Каталог **корня git** на сервере, например `/var/www/carfluencer` (рядом должны быть `backend/` и `deploy/`) |

На сервере перед первым деплоем: `git clone` репозитория в `DEPLOY_PATH`, один раз выполнить настройку из разделов 3–6 (`.env`, nginx, supervisor, cron).

Опционально усилить безопасность: в workflow добавить параметр `fingerprint` для `appleboy/ssh-action` (SHA256 хост-ключа сервера), см. [документацию action](https://github.com/appleboy/ssh-action).

---

## Логи приложения

См. **`docs/OPERATIONS/01_logging.md`** — ротация **`daily`**, опциональный JSON-файл, Slack, заголовок **`X-Request-ID`**.

---

## Чеклист «всё поднято»

- [ ] Открывается `/` или `/admin` без 502  
- [ ] Создан админ: `sudo -u www-data php artisan make:filament-user`  
- [ ] `php artisan migrate --force` без ошибок  
- [ ] `supervisorctl status` — **`carfluencer-queue`** `RUNNING`  
- [ ] Есть **`/etc/cron.d/carfluencer-laravel`** и `schedule:run` от **`www-data`**  
- [ ] При ClickHouse: `telemetry:test-clickhouse` OK  
- [ ] После синка в админке Heatmap показывает точки из `device_locations`  
- [ ] **`/`** и **`/media-owner/login`**, **`/advertiser/login`** отдают React (см. §8)  

Если нужен **один скрипт** с вашими путями — скопируйте и отредактируйте `deploy/vps-first-deploy.sh.example`.
