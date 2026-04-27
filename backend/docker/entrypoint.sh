#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Clear all compiled Laravel caches so route/config changes are picked up
# even when bootstrap/cache is persisted as a Docker volume.
php artisan optimize:clear
php artisan migrate --force

if [ "$RUN_SEEDERS" = "true" ]; then
    php artisan db:seed --force
elif [ "$RUN_SEEDERS" = "fresh" ]; then
    if php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); exit(\App\Models\User::query()->exists() ? 1 : 0);'; then
        php artisan db:seed --force
    fi
fi

exec apache2-foreground
