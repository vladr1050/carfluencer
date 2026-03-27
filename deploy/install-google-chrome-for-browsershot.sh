#!/usr/bin/env bash
#
# Google Chrome (.deb) для Browsershot на сервере: snap Chromium под PHP/Supervisor обычно не работает
# (cgroup snap, mkdir /var/www/snap). Этот скрипт ставит официальный пакет.
#
# Запуск: sudo bash deploy/install-google-chrome-for-browsershot.sh
#
set -euo pipefail

if [[ "${EUID:-0}" -ne 0 ]]; then
  echo "Запусти от root: sudo bash $0"
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive
ARCH="$(dpkg --print-architecture)"
case "$ARCH" in
  amd64) DEB_NAME="google-chrome-stable_current_amd64.deb" ;;
  arm64) DEB_NAME="google-chrome-stable_current_arm64.deb" ;;
  *)
    echo "Архитектура $ARCH не поддержана этим скриптом (нужны amd64 или arm64)."
    exit 1
    ;;
esac

apt-get update -qq
apt-get install -y -qq wget ca-certificates

TMP_DEB="$(mktemp /tmp/chrome-XXXXXX.deb)"
trap 'rm -f "$TMP_DEB"' EXIT
wget -qO "$TMP_DEB" "https://dl.google.com/linux/direct/${DEB_NAME}"

apt-get install -y "$TMP_DEB"

echo ""
echo "Готово. В backend/.env укажи:"
echo "  CAMPAIGN_REPORT_CHROME_PATH=/usr/bin/google-chrome-stable"
echo "Затем: cd backend && sudo -u www-data php artisan config:clear && sudo supervisorctl restart 'carfluencer-queue:*'"
