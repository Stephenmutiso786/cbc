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

MIGRATION_TIMEOUT="${MIGRATION_TIMEOUT:-120}"
if ! timeout "${MIGRATION_TIMEOUT}" php artisan migrate --force; then
    echo "Database migrations did not finish within ${MIGRATION_TIMEOUT}s. Check the Render database host, SSL CA, and credentials." >&2
    exit 1
fi

# Seeders are for first-time provisioning, not every container restart. Running
# them on every cold start unnecessarily delays Render's health check.
if [ "${RUN_DB_SEEDER:-false}" = "true" ]; then
    timeout "${MIGRATION_TIMEOUT}" php artisan db:seed --force
fi

php artisan optimize:clear
php artisan storage:link || true

exec php artisan serve --host 0.0.0.0 --port "${PORT:-10000}"
