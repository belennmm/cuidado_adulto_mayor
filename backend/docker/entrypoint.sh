#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

php artisan config:clear
php artisan migrate --force --seed

exec apache2-foreground
