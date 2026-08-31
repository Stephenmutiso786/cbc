#!/usr/bin/env sh
set -eu

mkdir -p bootstrap/cache storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chmod -R a+rwX bootstrap/cache storage public/storage 2>/dev/null || true

if [ -z "${APP_KEY:-}" ]; then
    export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
elif [ "${APP_KEY#base64:}" = "$APP_KEY" ]; then
    export APP_KEY="base64:${APP_KEY}"
fi

php artisan optimize:clear >/dev/null 2>&1 || true
php artisan storage:link >/dev/null 2>&1 || true

exec php artisan serve --host 0.0.0.0 --port "${PORT:-10000}"
