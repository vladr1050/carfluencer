#!/usr/bin/env bash
#
# npm install в backend/ от пользователя www-data (puppeteer для Browsershot / отчётов).
# Кэш npm — только в backend/.npm-cache (без ~/.npm под root).
# Чинит типичный EACCES: /var/www/.npm или ~/.npm у www-data с владельцем root.
#
# Запуск с корня репозитория:
#   sudo bash deploy/npm-install-backend-www-data.sh
# Или с произвольным корнем:
#   sudo bash deploy/npm-install-backend-www-data.sh /var/www/carfluencer
#
set -euo pipefail

if [[ "${EUID:-0}" -ne 0 ]]; then
  echo "Запусти от root: sudo bash $0"
  exit 1
fi

ROOT="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
BACKEND="$ROOT/backend"

if [[ ! -f "$BACKEND/package.json" ]]; then
  echo "Нет $BACKEND/package.json — проверь путь к репозиторию."
  exit 1
fi

mkdir -p "$BACKEND/.npm-cache"
chown -R www-data:www-data "$BACKEND/.npm-cache"

# После git pull каталог backend часто root:root — www-data не может создать node_modules.
chown www-data:www-data "$BACKEND/package.json" 2>/dev/null || true
if [[ -f "$BACKEND/package-lock.json" ]]; then
  chown www-data:www-data "$BACKEND/package-lock.json"
fi
if [[ -d "$BACKEND/node_modules" ]]; then
  chown -R www-data:www-data "$BACKEND/node_modules"
fi
chown www-data:www-data "$BACKEND"

WWW_HOME="$(getent passwd www-data | cut -d: -f6)"
if [[ -n "${WWW_HOME:-}" && -d "$WWW_HOME/.npm" ]]; then
  chown -R www-data:www-data "$WWW_HOME/.npm" || true
fi

if [[ -d /var/www/.npm ]]; then
  chown -R www-data:www-data /var/www/.npm || true
fi

export PUPPETEER_SKIP_DOWNLOAD=1
sudo -u www-data env NPM_CONFIG_CACHE="$BACKEND/.npm-cache" \
  npm --prefix "$BACKEND" install --no-fund --no-audit

echo "OK: backend/node_modules (в т.ч. puppeteer), кэш: $BACKEND/.npm-cache"
