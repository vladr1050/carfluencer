FROM node:22-bookworm AS node_binary

FROM php:8.4-fpm-bookworm

# libh3: Debian bookworm has no libh3-dev on arm64; build Uber H3 once for amd64 + arm64.
# michaellindahl/php-h3 uses C API geoToH3/h3ToGeo (H3 v3). v4 renamed symbols — stay on v3.x.
ARG H3_GIT_REF=v3.7.2

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libicu-dev \
    libffi-dev \
    build-essential \
    cmake \
    chromium \
    ca-certificates \
    fonts-liberation \
    libasound2 \
    libatk-bridge2.0-0 \
    libatk1.0-0 \
    libcairo2 \
    libcups2 \
    libdbus-1-3 \
    libdrm2 \
    libgbm1 \
    libglib2.0-0 \
    libnspr4 \
    libnss3 \
    libpango-1.0-0 \
    libx11-6 \
    libxcomposite1 \
    libxdamage1 \
    libxext6 \
    libxfixes3 \
    libxkbcommon0 \
    libxrandr2 \
    && git clone --depth 1 --branch "${H3_GIT_REF}" https://github.com/uber/h3.git /tmp/h3 \
    && cmake -S /tmp/h3 -B /tmp/h3/build -DCMAKE_BUILD_TYPE=Release -DBUILD_SHARED_LIBS=ON -DCMAKE_INSTALL_PREFIX=/usr/local \
    && cmake --build /tmp/h3/build -j"$(nproc)" \
    && cmake --install /tmp/h3/build \
    && ldconfig \
    && rm -rf /tmp/h3 \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql zip opcache bcmath intl ffi \
    && printf '%s\n' '; Impression Engine (Uber H3 via FFI)' 'ffi.enable=true' > "$PHP_INI_DIR/conf.d/docker-php-ffi-enable.ini" \
    && apt-get purge -y build-essential cmake \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

COPY --from=node_binary /usr/local/bin/node /usr/local/bin/node
COPY --from=node_binary /usr/local/bin/npm /usr/local/bin/npm
COPY --from=node_binary /usr/local/bin/npx /usr/local/bin/npx
COPY --from=node_binary /usr/local/lib/node_modules /usr/local/lib/node_modules

ENV CAMPAIGN_REPORT_BROWSER_DRIVER=browsershot \
    CAMPAIGN_REPORT_CHROME_PATH=/usr/bin/chromium

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Прод-настройки PHP по умолчанию (для dev можно смонтировать свой php.ini)
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
