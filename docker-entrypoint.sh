#!/usr/bin/env sh
set -eu

mkdir -p bootstrap/cache storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chmod -R a+rwX bootstrap/cache storage public/storage 2>/dev/null || true

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is required. Set the stable Laravel APP_KEY in Render." >&2
    exit 1
elif [ "${APP_KEY#base64:}" = "$APP_KEY" ]; then
    export APP_KEY="base64:${APP_KEY}"
fi

php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear
php artisan storage:link || true

exec php artisan serve --host 0.0.0.0 --port "${PORT:-10000}"
