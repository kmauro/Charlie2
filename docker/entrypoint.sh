#!/bin/sh

set -eu

cd /var/www/html

mkdir -p \
    storage/app/docker \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache

if [ -z "${APP_KEY:-}" ]; then
    key_file="storage/app/docker/application-key"

    if [ ! -s "$key_file" ]; then
        umask 077
        php artisan key:generate --show --no-ansi > "${key_file}.tmp"
        mv "${key_file}.tmp" "$key_file"
    fi

    APP_KEY="$(cat "$key_file")"
    export APP_KEY
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan optimize
fi

case "${1:-}" in
    php)
        exec gosu www-data "$@"
        ;;
    *)
        exec "$@"
        ;;
esac
