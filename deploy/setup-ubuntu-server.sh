#!/usr/bin/env bash
#
# Подготовка прод-среды на свежем Ubuntu (PHP 8.4, PostgreSQL, Nginx, Laravel).
# Запускать на СЕРВЕРЕ под root, из уже клонированного репозитория или с указанием пути.
#
#   cd /var/www/carfluencer
#   sudo bash deploy/setup-ubuntu-server.sh 'http://ТВОЙ_IP'
#
# Переменные окружения:
#   REPO_ROOT=/var/www/carfluencer   — корень git (по умолчанию текущий каталог, если в нём есть backend/)
#
set -euo pipefail

if [[ "${EUID:-0}" -ne 0 ]]; then
  echo "Запусти от root: sudo bash $0 'http://ТВОЙ_IP_ИЛИ_ДОМЕН'"
  exit 1
fi

APP_URL="${1:?Укажи публичный URL без слэша в конце, например: http://135.181.36.104}"

if [[ -d "${REPO_ROOT:-}/backend" ]]; then
  :
elif [[ -d "$(pwd)/backend" ]]; then
  REPO_ROOT="$(pwd)"
else
  REPO_ROOT="${REPO_ROOT:-/var/www/carfluencer}"
fi

if [[ ! -f "$REPO_ROOT/backend/.env.production.example" ]]; then
  echo "Не найден $REPO_ROOT/backend/.env.production.example — задай REPO_ROOT или cd в корень клона."
  exit 1
fi

SERVER_NAME="$(echo "$APP_URL" | sed -E 's#^https?://##' | sed 's#/.*##')"

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq software-properties-common ca-certificates lsb-release
# Symfony 8 / текущий composer.lock требуют PHP >= 8.4
add-apt-repository -y ppa:ondrej/php
apt-get update -qq
apt-get install -y -qq \
  nginx \
  postgresql postgresql-contrib \
  php8.4-fpm php8.4-cli php8.4-pgsql php8.4-xml php8.4-mbstring \
  php8.4-curl php8.4-zip php8.4-bcmath php8.4-intl php8.4-gd \
  git unzip curl composer python3 supervisor

systemctl enable --now php8.4-fpm postgresql

DB_PASS="$(openssl rand -hex 16)"

sudo -u postgres psql -v ON_ERROR_STOP=1 -c "CREATE USER evo WITH PASSWORD '$DB_PASS';" 2>/dev/null \
  || sudo -u postgres psql -v ON_ERROR_STOP=1 -c "ALTER USER evo WITH PASSWORD '$DB_PASS';"

if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='evo'" | grep -q 1; then
  sudo -u postgres psql -v ON_ERROR_STOP=1 -c "CREATE DATABASE evo OWNER evo;"
fi

ENV_FILE="$REPO_ROOT/backend/.env"
cp -a "$REPO_ROOT/backend/.env.production.example" "$ENV_FILE"

# Подстановка URL и пароля БД (Python надёжнее sed при спецсимволах)
python3 -c "
import pathlib, sys
p = pathlib.Path(sys.argv[1])
t = p.read_text()
t = t.replace('http://YOUR_SERVER_IP_OR_DOMAIN', sys.argv[2])
t = t.replace('CHANGE_ME_DB_PASSWORD', sys.argv[3])
p.write_text(t)
" "$ENV_FILE" "$APP_URL" "$DB_PASS"

# Чтение .env для www-data (очередь + cron)
chgrp www-data "$ENV_FILE"
chmod 640 "$ENV_FILE"

cd "$REPO_ROOT/backend"
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --no-interaction --optimize-autoloader

php artisan key:generate --force --no-interaction
php artisan migrate --force --no-interaction
php artisan storage:link --force --no-interaction || true
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
php artisan filament:optimize --no-interaction 2>/dev/null || true

chown -R www-data:www-data "$REPO_ROOT/backend/storage" "$REPO_ROOT/backend/bootstrap/cache"
chmod -R ug+rwx "$REPO_ROOT/backend/storage" "$REPO_ROOT/backend/bootstrap/cache"
touch "$REPO_ROOT/backend/storage/logs/worker.log"
chown www-data:www-data "$REPO_ROOT/backend/storage/logs/worker.log"

NGINX_SITE=/etc/nginx/sites-available/carfluencer
cp -a "$REPO_ROOT/deploy/nginx-carfluencer.conf.example" "$NGINX_SITE"
sed -i "s/135.181.36.104/${SERVER_NAME}/g" "$NGINX_SITE"
ln -sf "$NGINX_SITE" /etc/nginx/sites-enabled/carfluencer
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
nginx -t
# Часто на Hetzner/образах уже слушает Apache — освобождаем 80/443 для Nginx
if systemctl list-unit-files apache2.service 2>/dev/null | grep -q apache2; then
  systemctl stop apache2 2>/dev/null || true
  systemctl disable apache2 2>/dev/null || true
fi
systemctl enable nginx
systemctl restart nginx

# --- Очередь (Supervisor) и планировщик (cron) ---
SUP_CONF=/etc/supervisor/conf.d/carfluencer-queue.conf
cp -a "$REPO_ROOT/deploy/supervisor-laravel.conf.example" "$SUP_CONF"
sed -i "s#/var/www/carfluencer#${REPO_ROOT}#g" "$SUP_CONF"

CRON_FILE=/etc/cron.d/carfluencer-laravel
{
  echo "SHELL=/bin/sh"
  echo "PATH=/usr/sbin:/usr/bin:/sbin:/bin"
  echo ""
  echo "* * * * * www-data cd ${REPO_ROOT}/backend && /usr/bin/php8.4 artisan schedule:run >> /dev/null 2>&1"
} > "$CRON_FILE"
chmod 644 "$CRON_FILE"

systemctl enable supervisor
supervisorctl reread
supervisorctl update
supervisorctl start "carfluencer-queue:*" 2>/dev/null || supervisorctl restart "carfluencer-queue:*" || true

echo ""
echo "=== Готово ==="
echo "APP_URL:       $APP_URL"
echo "Nginx server:  $SERVER_NAME"
echo "PostgreSQL:    database=evo user=evo password=$DB_PASS"
echo "(Сохрани пароль БД в надёжном месте; он уже записан в $ENV_FILE)"
echo ""
echo "Открой в браузере: ${APP_URL}/admin"
echo "Создай админа:   cd $REPO_ROOT/backend && sudo -u www-data php artisan make:filament-user"
echo "Supervisor:      supervisorctl status carfluencer-queue:*"
echo "Cron:            $CRON_FILE"
echo "Артефакты:       deploy/README.md"
