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

# Render stores multiline environment values as text. PDO needs the Aiven CA
# certificate as a readable PEM file, so materialize it before Laravel boots.
case "${MYSQL_ATTR_SSL_CA:-}" in
    -----BEGIN\ CERTIFICATE-----*)
        printf '%b' "$MYSQL_ATTR_SSL_CA" > /tmp/aiven-ca.pem
        chmod 0644 /tmp/aiven-ca.pem
        export MYSQL_ATTR_SSL_CA=/tmp/aiven-ca.pem
        ;;
esac

php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear
php artisan storage:link || true

exec php artisan serve --host 0.0.0.0 --port "${PORT:-10000}"
