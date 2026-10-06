#!/bin/sh
set -e

if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
fi

if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Remove compiled package/provider manifests before Laravel boots. This also
# handles persisted cache volumes after a Composer dependency is removed.
find bootstrap/cache -maxdepth 1 -type f -name '*.php' -delete

# Clear all remaining Laravel caches so route/config changes are picked up.
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
