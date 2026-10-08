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

if [ "${DB_CONNECTION:-mysql}" = "mysql" ]; then
    attempts=0

    until php -r '
        try {
            new PDO(
                sprintf(
                    "mysql:host=%s;port=%s;dbname=%s",
                    getenv("DB_HOST"),
                    getenv("DB_PORT"),
                    getenv("DB_DATABASE"),
                ),
                getenv("DB_USERNAME"),
                getenv("DB_PASSWORD"),
            );
        } catch (Throwable) {
            exit(1);
        }
    '; do
        attempts=$((attempts + 1))

        if [ "$attempts" -ge 30 ]; then
            echo "Database did not become ready after 60 seconds." >&2
            exit 1
        fi

        echo "Waiting for database connection ($attempts/30)..."
        sleep 2
    done
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
