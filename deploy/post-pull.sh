#!/usr/bin/env bash
# Выполняется НА СЕРВЕРЕ после `git fetch` / `git pull` из корня репозитория (монорепо).
# Путь: ./deploy/post-pull.sh от корня клона (рядом с каталогом backend/).
set -euo pipefail

if [[ "${EUID:-0}" -eq 0 ]]; then
  export COMPOSER_ALLOW_SUPERUSER=1
fi

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT/backend"

composer install --no-dev --no-interaction --optimize-autoloader

php artisan migrate --force --no-interaction

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize || true

# Воркеры Supervisor сами перезапустятся после выхода процесса (см. deploy/supervisor-laravel.conf.example)
php artisan queue:restart || true

echo "Deploy script finished OK."
