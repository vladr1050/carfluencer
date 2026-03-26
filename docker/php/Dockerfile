FROM node:22-bookworm AS node_binary

FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    libicu-dev \
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
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql zip opcache bcmath intl \
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
