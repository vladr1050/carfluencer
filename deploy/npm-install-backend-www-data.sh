#!/usr/bin/env bash
#
# npm install для Browsershot (пакет puppeteer в backend/node_modules).
# Запуск: от root (sudo bash …). Сам npm install идёт от root — без проблем с правами
# на node_modules и кэшами; после установки node_modules отдаётся www-data (очередь / PHP).
# Chrome из Puppeteer не качаем — в .env задаётся CAMPAIGN_REPORT_CHROME_PATH (snap и т.д.).
#
# Запуск с корня репозитория:
#   sudo bash deploy/npm-install-backend-www-data.sh
# Или:
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

# Если раньше ставили от www-data — починим типичные root-owned кэши
if [[ -d /var/www/.npm ]]; then
  chown -R root:root /var/www/.npm 2>/dev/null || true
fi
WWW_HOME="$(getent passwd www-data | cut -d: -f6)"
if [[ -n "${WWW_HOME:-}" && -d "$WWW_HOME/.npm" ]]; then
  chown -R www-data:www-data "$WWW_HOME/.npm" || true
fi

# Явно в env процесса npm (в т.ч. postinstall puppeteer) + дублирует backend/.npmrc
export NPM_CONFIG_CACHE="$BACKEND/.npm-cache"
export PUPPETEER_SKIP_DOWNLOAD=1

echo "npm install (root) в $BACKEND, PUPPETEER_SKIP_DOWNLOAD=1 …"
npm --prefix "$BACKEND" install --no-fund --no-audit

echo "chown node_modules → www-data …"
chown -R www-data:www-data "$BACKEND/node_modules"
if [[ -f "$BACKEND/package-lock.json" ]]; then
  chown www-data:www-data "$BACKEND/package-lock.json"
fi

echo "OK: backend/node_modules, кэш npm: $BACKEND/.npm-cache"
