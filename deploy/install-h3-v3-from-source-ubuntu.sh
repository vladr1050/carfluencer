#!/usr/bin/env bash
#
# Сборка Uber H3 v3.7.2 из исходников (совместимость с michaellindahl/php-h3: geoToH3 / h3ToGeo).
# Нужен, если пакет libh3-dev недоступен или отдаёт только v4 (другой C API).
#
#   sudo bash deploy/install-h3-v3-from-source-ubuntu.sh
#
set -euo pipefail

if [[ "${EUID:-0}" -ne 0 ]]; then
  echo "Запусти от root: sudo bash $0"
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq build-essential cmake git

H3_REF="${H3_REF:-v3.7.2}"
rm -rf /tmp/h3
git clone --depth 1 --branch "${H3_REF}" https://github.com/uber/h3.git /tmp/h3
cmake -S /tmp/h3 -B /tmp/h3/build -DCMAKE_BUILD_TYPE=Release -DBUILD_SHARED_LIBS=ON -DCMAKE_INSTALL_PREFIX=/usr/local
cmake --build /tmp/h3/build -j"$(nproc)"
cmake --install /tmp/h3/build
ldconfig
rm -rf /tmp/h3

echo "OK: libh3 установлен в /usr/local/lib (проверка: ldconfig -p | grep h3)"
