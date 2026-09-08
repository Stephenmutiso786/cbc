FROM php:8.2-cli-bookworm AS vendor
WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        ca-certificates \
        curl \
        gnupg \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        libexif-dev \
        libpq-dev \
    && install -d /usr/share/postgresql-common/pgdg \
    && curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc \
    && echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt bookworm-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends postgresql-client-17 \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring xml zip gd exif \
    && rm -rf /var/lib/apt/lists/*

RUN printf 'upload_max_filesize=25M\npost_max_size=30M\nmemory_limit=256M\n' > /usr/local/etc/php/conf.d/uploads.ini

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
        ca-certificates \
        curl \
        gnupg \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        libexif-dev \
        libpq-dev \
    && install -d /usr/share/postgresql-common/pgdg \
    && curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc -o /usr/share/postgresql-common/pgdg/apt.postgresql.org.asc \
    && echo "deb [signed-by=/usr/share/postgresql-common/pgdg/apt.postgresql.org.asc] https://apt.postgresql.org/pub/repos/apt bookworm-pgdg main" > /etc/apt/sources.list.d/pgdg.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends postgresql-client-17 \
    && docker-php-ext-install pdo_mysql pdo_pgsql mbstring xml zip gd exif \
    && rm -rf /var/lib/apt/lists/*

RUN printf 'upload_max_filesize=25M\npost_max_size=30M\nmemory_limit=256M\n' > /usr/local/etc/php/conf.d/uploads.ini

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN mkdir -p bootstrap/cache storage/app/public storage/framework/{cache,sessions,views} storage/logs public/storage \
    && chmod -R a+rwX bootstrap/cache storage public/storage

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 10000
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
