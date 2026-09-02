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

if [ "${DB_CONNECTION:-mysql}" = "pgsql" ] && [ -z "${DB_URL:-${DATABASE_URL:-}}" ]; then
    echo "DB_URL (or DATABASE_URL) is required when DB_CONNECTION=pgsql. Add the Neon PostgreSQL connection string in Render." >&2
    exit 1
fi

# Redis is opt-in. This keeps local installs and deployments without a Redis
# URL on the durable database queue instead of failing during boot.
if [ "${USE_REDIS:-false}" = "true" ]; then
    if [ -z "${REDIS_URL:-}" ]; then
        echo "USE_REDIS=true requires REDIS_URL. Add the Render Key Value internal URL or disable USE_REDIS." >&2
        exit 1
    fi
    # Render may retain an older REDIS_CLIENT=phpredis variable. The image
    # uses Predis, so fall back automatically when the PHP extension is absent.
    if [ "${REDIS_CLIENT:-predis}" = "phpredis" ] && ! php -r 'exit(class_exists("Redis") ? 0 : 1);'; then
        export REDIS_CLIENT=predis
        echo "PhpRedis is unavailable; using the bundled Predis client."
    else
        export REDIS_CLIENT="${REDIS_CLIENT:-predis}"
    fi
    export QUEUE_CONNECTION=redis
    export CACHE_STORE=redis
    export SESSION_DRIVER=redis
fi

MIGRATION_TIMEOUT="${MIGRATION_TIMEOUT:-120}"
if ! timeout "${MIGRATION_TIMEOUT}" php artisan migrate --force; then
    echo "Database migrations did not finish within ${MIGRATION_TIMEOUT}s. Check the Render database host, SSL CA, and credentials." >&2
    exit 1
fi

# Provision a new empty database once, but never reseed an existing school on
# ordinary restarts. The seeders use firstOrCreate/syncRoles and are idempotent.
if [ "${RUN_DB_SEEDER:-false}" = "true" ]; then
    timeout "${MIGRATION_TIMEOUT}" php artisan db:seed --force
else
    if ! php artisan tinker --execute="exit(\\App\\Models\\User::query()->exists() ? 0 : 1);" >/dev/null 2>&1; then
        echo "No users found. Provisioning the initial administrator accounts..."
        timeout "${MIGRATION_TIMEOUT}" php artisan db:seed --force
    fi
fi

php artisan optimize:clear
php artisan storage:link || true

# Run queued SMS, reports, backups, and integrations outside the web request.
# Render's web service can host this worker while the app is small; move it to
# a dedicated worker service when the deployment is scaled horizontally.
if [ "${QUEUE_CONNECTION:-sync}" != "sync" ] && [ "${QUEUE_WORKER:-true}" = "true" ]; then
    php artisan queue:work "${QUEUE_CONNECTION}" --sleep=2 --tries=3 --timeout=120 --no-interaction &
    echo "Queue worker started using ${QUEUE_CONNECTION} connection."
fi

# PHP's CLI server supports multiple worker processes. Override
# PHP_CLI_SERVER_WORKERS when sizing a larger Render instance.
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-${WEB_CONCURRENCY:-4}}"
exec php artisan serve --host 0.0.0.0 --port "${PORT:-10000}"
