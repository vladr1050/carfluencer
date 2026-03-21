#!/usr/bin/env bash
#
# Подготовка прод-среды на свежем Ubuntu (PHP 8.3, PostgreSQL, Nginx, Laravel).
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
apt-get install -y -qq \
  nginx \
  postgresql postgresql-contrib \
  php8.3-fpm php8.3-cli php8.3-pgsql php8.3-xml php8.3-mbstring \
  php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl php8.3-gd \
  git unzip curl composer

systemctl enable --now php8.3-fpm postgresql nginx

DB_PASS="$(openssl rand -hex 16)"

sudo -u postgres psql -v ON_ERROR_STOP=1 -c "CREATE USER evo WITH PASSWORD '$DB_PASS';" 2>/dev/null \
  || sudo -u postgres psql -v ON_ERROR_STOP=1 -c "ALTER USER evo WITH PASSWORD '$DB_PASS';"

if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='evo'" | grep -q 1; then
  sudo -u postgres psql -v ON_ERROR_STOP=1 -c "CREATE DATABASE evo OWNER evo;"
fi

ENV_FILE="$REPO_ROOT/backend/.env"
cp -a "$REPO_ROOT/backend/.env.production.example" "$ENV_FILE"

# Подстановка URL и пароля БД (без "|" в APP_URL)
perl -pi -e 's{\Qhttp://YOUR_SERVER_IP_OR_DOMAIN\E}{'"$APP_URL"'}{g}' "$ENV_FILE"
perl -pi -e 's{\QCHANGE_ME_DB_PASSWORD\E}{'"$DB_PASS"'}{g}' "$ENV_FILE"

cd "$REPO_ROOT/backend"
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

NGINX_SITE=/etc/nginx/sites-available/carfluencer
cp -a "$REPO_ROOT/deploy/nginx-carfluencer.conf.example" "$NGINX_SITE"
sed -i "s/135.181.36.104/${SERVER_NAME}/g" "$NGINX_SITE"
ln -sf "$NGINX_SITE" /etc/nginx/sites-enabled/carfluencer
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
nginx -t
systemctl reload nginx

echo ""
echo "=== Готово ==="
echo "APP_URL:       $APP_URL"
echo "Nginx server:  $SERVER_NAME"
echo "PostgreSQL:    database=evo user=evo password=$DB_PASS"
echo "(Сохрани пароль БД в надёжном месте; он уже записан в $ENV_FILE)"
echo ""
echo "Открой в браузере: ${APP_URL}/admin"
echo "Очередь: supervisor + cron schedule:run — см. docs/DEPLOY/12_vps_production.md §6"
