#!/bin/sh
set -e

cd /var/www/html

# The storage volume starts out empty, so recreate the folders Laravel expects.
mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

as_www_data() {
    setpriv --reuid=www-data --regid=www-data --init-groups "$@"
}

# Without APP_KEY in .env, the app container generates a key once and keeps it in the
# shared storage volume, where the queue and scheduler containers read it too.
KEY_FILE=storage/app/private/app-key

if [ -z "${APP_KEY:-}" ]; then
    if [ ! -s "$KEY_FILE" ] && [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
        (umask 077 && echo "base64:$(head -c 32 /dev/urandom | base64 -w 0)" > "$KEY_FILE")
        chown www-data:www-data "$KEY_FILE"
        echo "Generated an application key and saved it in $KEY_FILE."
    fi

    if [ ! -s "$KEY_FILE" ]; then
        echo "APP_KEY is not set and no generated key was found in $KEY_FILE." >&2
        exit 1
    fi

    APP_KEY="$(cat "$KEY_FILE")"
    export APP_KEY
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    attempts=0
    until as_www_data php artisan migrate --force --no-interaction; do
        attempts=$((attempts + 1))
        if [ "$attempts" -ge 30 ]; then
            echo "The database did not become available, giving up." >&2
            exit 1
        fi
        echo "Waiting for the database..."
        sleep 2
    done

    # Create the first main admin from .env. Once any main admin exists this does nothing.
    if [ -n "${ADMIN_EMAIL:-}" ]; then
        as_www_data php artisan signup:create-admin "$ADMIN_EMAIL" --name="${ADMIN_NAME:-}" --if-none --no-interaction
    fi
fi

as_www_data php artisan optimize --no-interaction > /dev/null

# Apache starts as root and drops privileges itself; everything else runs as www-data.
if [ "$1" = "apache2-foreground" ]; then
    exec "$@"
fi

exec setpriv --reuid=www-data --regid=www-data --init-groups "$@"
