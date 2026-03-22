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

# Добавить в .env только отсутствующие TELEMETRY_* из deploy/telemetry.env.fragment (идемпотентно)
php artisan telemetry:ensure-env --no-interaction || true

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:optimize || true

# Воркеры Supervisor сами перезапустятся после выхода процесса (см. deploy/supervisor-laravel.conf.example)
php artisan queue:restart || true

if grep -qE '^TELEMETRY_CLICKHOUSE_ENABLED=(true|1)$' .env 2>/dev/null; then
  php artisan telemetry:test-clickhouse --no-interaction || echo "[post-pull] Warning: telemetry:test-clickhouse failed (check CH URL / firewall)."
fi

# Optional: rebuild advertiser / media-owner SPA (requires Node + npm on the server).
# export CARFLUENCER_FRONTEND_BUILD=1 before post-pull, or set in the SSH deploy script.
if [[ "${CARFLUENCER_FRONTEND_BUILD:-0}" == "1" ]] && command -v npm >/dev/null 2>&1; then
  echo "[post-pull] Building frontend (CARFLUENCER_FRONTEND_BUILD=1)..."
  cd "$ROOT/frontend"
  npm ci --no-audit --no-fund
  npm run build
  echo "[post-pull] frontend/dist updated."
fi

echo "Deploy script finished OK."
