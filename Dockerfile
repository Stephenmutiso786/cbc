FROM php:8.2-cli-bookworm AS vendor
WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        libexif-dev \
    && docker-php-ext-install pdo_mysql mbstring xml zip gd exif \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
COPY app/Support/polyfills.php app/Support/polyfills.php
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader --no-scripts

FROM node:20-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM php:8.2-cli-bookworm
WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        libexif-dev \
    && docker-php-ext-install pdo_mysql mbstring xml zip gd exif \
    && rm -rf /var/lib/apt/lists/*

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN composer dump-autoload --no-interaction --optimize --no-scripts \
    && php artisan package:discover --ansi

RUN php artisan storage:link || true

EXPOSE 10000
CMD ["sh", "-c", "php artisan serve --host 0.0.0.0 --port ${PORT:-10000}"]
