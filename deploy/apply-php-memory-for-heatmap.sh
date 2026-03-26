#!/usr/bin/env bash
#
# Поднимает memory_limit для PHP-FPM + CLI и для воркера очереди (Supervisor).
# Решает: Allowed memory size of 134217728 bytes exhausted при heatmap / отчётах.
#
# Запуск на сервере из корня репозитория:
#   cd /var/www/carfluencer && sudo bash deploy/apply-php-memory-for-heatmap.sh
#
set -euo pipefail

if [[ "${EUID:-0}" -ne 0 ]]; then
  echo "Запусти от root: sudo bash $0"
  exit 1
fi

PHP_VER="${PHP_VERSION:-8.4}"
MEM="${PHP_MEMORY_LIMIT:-512M}"

echo "PHP ${PHP_VER}, memory_limit=${MEM}"

for INI in "/etc/php/${PHP_VER}/fpm/php.ini" "/etc/php/${PHP_VER}/cli/php.ini"; do
  if [[ -f "$INI" ]]; then
    sed -ri "s/^;?[[:space:]]*memory_limit[[:space:]]*=.*/memory_limit = ${MEM}/" "$INI"
    echo "  updated: $INI"
  else
    echo "  skip (нет файла): $INI"
  fi
done

# В pool.d часто стоит php_admin_value[memory_limit] = 128M — это ПЕРЕБИВАЕТ php.ini для веб-запросов
POOL_DIR="/etc/php/${PHP_VER}/fpm/pool.d"
if [[ -d "$POOL_DIR" ]]; then
  shopt -s nullglob
  for POOL in "$POOL_DIR"/*.conf; do
    if grep -qE '^[;[:space:]]*php_(admin_)?value\[memory_limit\]' "$POOL"; then
      sed -ri \
        -e 's/^;?[[:space:]]*php_admin_value\[memory_limit\][[:space:]]*=.*/php_admin_value[memory_limit] = '"${MEM}"'/' \
        -e 's/^;?[[:space:]]*php_value\[memory_limit\][[:space:]]*=.*/php_admin_value[memory_limit] = '"${MEM}"'/' \
        "$POOL"
      echo "  updated pool: $POOL (php_admin_value[memory_limit])"
    fi
  done
  shopt -u nullglob
fi

if systemctl is-active --quiet "php${PHP_VER}-fpm" 2>/dev/null; then
  systemctl reload "php${PHP_VER}-fpm"
  echo "  reloaded: php${PHP_VER}-fpm"
fi

SUP_CONF="${SUPERVISOR_QUEUE_CONF:-/etc/supervisor/conf.d/carfluencer-queue.conf}"
if [[ -f "$SUP_CONF" ]]; then
  if grep -qE '^command=.*/php8\.4[[:space:]]+' "$SUP_CONF" && ! grep -qE '^command=.*php8\.4[[:space:]]+-d[[:space:]]+memory_limit=' "$SUP_CONF"; then
    sed -ri 's#^command=(/usr/bin/php8\.4)[[:space:]]+#command=\1 -d memory_limit='"${MEM}"' #' "$SUP_CONF"
    echo "  updated supervisor: $SUP_CONF"
  elif grep -qE '^command=.*-d[[:space:]]+memory_limit=' "$SUP_CONF"; then
    echo "  supervisor уже с -d memory_limit: $SUP_CONF"
  else
    echo "  supervisor: не распознана command= (правь вручную): $SUP_CONF"
  fi
else
  echo "  нет $SUP_CONF — пропуск Supervisor (задай SUPERVISOR_QUEUE_CONF=... при необходимости)"
fi

if command -v supervisorctl >/dev/null 2>&1; then
  supervisorctl reread 2>/dev/null || true
  supervisorctl update 2>/dev/null || true
  supervisorctl restart 'carfluencer-queue:*' 2>/dev/null || true
  echo "  supervisorctl: reread/update/restart carfluencer-queue (если программа есть)"
fi

echo ""
echo "Готово. CLI: php -i | grep memory_limit"
php -i 2>/dev/null | grep -i '^memory_limit' || true
echo ""
echo "FPM pool (если пусто — лимит только из php.ini):"
grep -hE 'php_(admin_)?value\[memory_limit\]|^memory_limit' /etc/php/${PHP_VER}/fpm/pool.d/*.conf 2>/dev/null || true
