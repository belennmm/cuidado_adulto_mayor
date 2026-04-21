#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Clear all compiled Laravel caches so route/config changes are picked up
# even when bootstrap/cache is persisted as a Docker volume.
php artisan optimize:clear
php artisan migrate --force

exec apache2-foreground
