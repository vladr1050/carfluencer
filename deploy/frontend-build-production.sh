#!/usr/bin/env bash
# Сборка React-порталов (media owner / advertiser) для продакшена.
# Из корня репозитория:
#
#   bash deploy/frontend-build-production.sh
#   # или если API на другом origin:
#   API_URL=https://www.carplace.lv bash deploy/frontend-build-production.sh
#
# Результат: frontend/dist — залей на сервер в /var/www/carfluencer/frontend/dist/
# (rsync/scp) и перезагрузи nginx. Конфиг: deploy/nginx-carfluencer.conf.example
#
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# Same-origin (типовой Nginx): не задавайте API_URL — VITE_API_URL будет пустым → относительный /api
export VITE_API_URL="${API_URL-}"

cd "$REPO_ROOT/frontend"
npm ci
npm run build

echo ""
echo "=== Готово: $REPO_ROOT/frontend/dist ==="
echo "Пример выкладки (на macOS: COPYFILE_DISABLE=1 tar … — без мусорных ._ файлов):"
echo "  COPYFILE_DISABLE=1 tar -C \"$REPO_ROOT/frontend/dist\" -czf /tmp/fe.tgz ."
echo "  scp /tmp/fe.tgz user@server:/tmp/ && ssh user@server 'sudo tar -xzf /tmp/fe.tgz -C /var/www/carfluencer/frontend/dist && sudo systemctl reload nginx'"
