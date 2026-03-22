#!/usr/bin/env bash
#
# Одноразово на VPS (DNS уже указывает на сервер): домен www.carplace.lv, Nginx, Laravel .env, кэш.
#
# Запуск из корня git-клона (рядом с backend/), с правами root:
#   cd /var/www/carfluencer   # или ваш DEPLOY_PATH
#   sudo bash deploy/apply-carplace-lv-on-server.sh
#
# Что делает:
#   • подставляет в backend/.env: APP_URL, FRONTEND_URL, CORS, Sanctum, SESSION_* под carplace.lv
#   • копирует deploy/nginx-carfluencer.conf.example → /etc/nginx/sites-available/carfluencer
#     с заменой /var/www/carfluencer на фактический путь к репозиторию
#   • nginx -t && reload
#   • php artisan config:cache (от www-data)
#
# TLS: если ещё только HTTP, после скрипта выполните:
#   sudo certbot --nginx -d www.carplace.lv -d carplace.lv
#
# Пока нет HTTPS, сессии в браузере не заработают с secure-cookie. Временно:
#   sudo CARPLACE_SESSION_SECURE=false bash deploy/apply-carplace-lv-on-server.sh
# После certbot снова запустите без переменной (или CARPLACE_SESSION_SECURE=true).
#
set -euo pipefail

SESSION_SECURE_VALUE="${CARPLACE_SESSION_SECURE:-true}"

if [[ "${EUID:-0}" -ne 0 ]]; then
  echo "Нужен root: sudo bash $0"
  exit 1
fi

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="$ROOT/backend/.env"
NGINX_SRC="$ROOT/deploy/nginx-carfluencer.conf.example"
NGINX_DST="/etc/nginx/sites-available/carfluencer"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Нет файла $ENV_FILE — создайте .env (см. backend/.env.production.example)."
  exit 1
fi

if [[ ! -f "$NGINX_SRC" ]]; then
  echo "Нет $NGINX_SRC"
  exit 1
fi

echo "=== Репозиторий: $ROOT ==="

python3 - "$ENV_FILE" "$SESSION_SECURE_VALUE" <<'PY'
import re
import sys

path = sys.argv[1]
secure = sys.argv[2]
vals = {
    "APP_URL": "https://www.carplace.lv",
    "FRONTEND_URL": "https://www.carplace.lv",
    "CORS_ALLOWED_ORIGINS": "https://www.carplace.lv,https://carplace.lv",
    "SANCTUM_STATEFUL_DOMAINS": "www.carplace.lv,carplace.lv",
    "SESSION_DOMAIN": ".carplace.lv",
    "SESSION_SECURE_COOKIE": secure,
    "SESSION_SAME_SITE": "lax",
}

with open(path, encoding="utf-8") as f:
    lines = f.readlines()

keys_done = set()
out = []
key_re = re.compile(r"^([A-Za-z_][A-Za-z0-9_]*)=")

for line in lines:
    m = key_re.match(line)
    if m and m.group(1) in vals:
        k = m.group(1)
        out.append(f"{k}={vals[k]}\n")
        keys_done.add(k)
    else:
        out.append(line)

for k, v in vals.items():
    if k not in keys_done:
        if out and not out[-1].endswith("\n"):
            out[-1] += "\n"
        out.append(f"{k}={v}\n")

with open(path, "w", encoding="utf-8") as f:
    f.writelines(out)

print("Обновлён:", path)
for k in vals:
    print(f"  {k}={vals[k]}")
PY

TMP_NGINX="$(mktemp)"
sed "s|/var/www/carfluencer|${ROOT}|g" "$NGINX_SRC" > "$TMP_NGINX"
install -m 0644 "$TMP_NGINX" "$NGINX_DST"
rm -f "$TMP_NGINX"

ln -sf "$NGINX_DST" /etc/nginx/sites-enabled/carfluencer
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true

nginx -t
systemctl reload nginx

if id www-data &>/dev/null; then
  WEB_USER=www-data
elif id nginx &>/dev/null; then
  WEB_USER=nginx
else
  WEB_USER=root
fi

sudo -u "$WEB_USER" bash -c "cd \"$ROOT/backend\" && php artisan config:clear && php artisan config:cache"
echo "=== Готово. Проверьте https://www.carplace.lv (после certbot) и /admin ==="
echo "=== Без сертификата сначала откройте http://www.carplace.lv — затем: certbot --nginx ... ==="
