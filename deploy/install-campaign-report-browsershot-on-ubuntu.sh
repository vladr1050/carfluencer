#!/usr/bin/env bash
#
# Установка Node.js + Chromium для Browsershot на уже развёрнутом Ubuntu-сервере
# (без полного прогона setup-ubuntu-server.sh). Запуск: sudo bash deploy/install-campaign-report-browsershot-on-ubuntu.sh
#
set -euo pipefail

if [[ "${EUID:-0}" -ne 0 ]]; then
  echo "Запусти от root: sudo bash $0"
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq ca-certificates curl gnupg

curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt-get update -qq
apt-get install -y -qq nodejs

if ! apt-get install -y -qq chromium; then
  apt-get install -y -qq chromium-browser || true
fi

# libasound2 на Ubuntu 24.04+ часто заменён на libasound2t64 — ставим то, что есть
apt-get install -y -qq libasound2 2>/dev/null || apt-get install -y -qq libasound2t64 2>/dev/null || true

apt-get install -y -qq \
  fonts-liberation libatk-bridge2.0-0 libatk1.0-0 libcairo2 libcups2 \
  libdbus-1-3 libdrm2 libgbm1 libglib2.0-0 libnspr4 libnss3 libpango-1.0-0 \
  libx11-6 libxcomposite1 libxdamage1 libxext6 libxfixes3 libxkbcommon0 libxrandr2 \
  || true

CHROME_BIN="$(command -v chromium 2>/dev/null || command -v chromium-browser 2>/dev/null || command -v google-chrome-stable 2>/dev/null || true)"
echo "node: $(command -v node) ($(node -v))"
echo "chromium: ${CHROME_BIN:-НЕ НАЙДЕН}"

if [[ -z "$CHROME_BIN" ]]; then
  echo "Установи Chromium/Google Chrome вручную и добавь в backend/.env:"
  echo "  CAMPAIGN_REPORT_BROWSER_DRIVER=browsershot"
  echo "  CAMPAIGN_REPORT_CHROME_PATH=/путь/к/chrome"
  exit 1
fi

echo ""
echo "Добавь в backend/.env (или раскомментируй в .env.production.example):"
echo "CAMPAIGN_REPORT_BROWSER_DRIVER=browsershot"
echo "CAMPAIGN_REPORT_CHROME_PATH=$CHROME_BIN"
echo ""
echo "Затем: cd backend && sudo -u www-data php artisan config:clear && supervisorctl restart 'carfluencer-queue:*'"
