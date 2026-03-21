#!/usr/bin/env bash
# Сборка React-порталов (media owner / advertiser) для продакшена.
# Из корня репозитория:
#
#   API_URL=http://YOUR_HOST_OR_IP bash deploy/frontend-build-production.sh
#
# Результат: frontend/dist — залей на сервер в /var/www/carfluencer/frontend/dist/
# (rsync/scp) и перезагрузи nginx. Конфиг: deploy/nginx-carfluencer.conf.example
#
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export VITE_API_URL="${API_URL:?Задай API_URL, например: http://135.181.36.104}"

cd "$REPO_ROOT/frontend"
npm ci
npm run build

echo ""
echo "=== Готово: $REPO_ROOT/frontend/dist ==="
echo "Пример выкладки (на macOS: COPYFILE_DISABLE=1 tar … — без мусорных ._ файлов):"
echo "  COPYFILE_DISABLE=1 tar -C \"$REPO_ROOT/frontend/dist\" -czf /tmp/fe.tgz ."
echo "  scp /tmp/fe.tgz user@server:/tmp/ && ssh user@server 'sudo tar -xzf /tmp/fe.tgz -C /var/www/carfluencer/frontend/dist && sudo systemctl reload nginx'"
