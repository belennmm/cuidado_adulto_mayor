#!/bin/sh
set -e

required_variables="APP_KEY APP_URL DB_PASSWORD CORS_ALLOWED_ORIGINS"
for variable_name in $required_variables; do
    eval "variable_value=\${$variable_name:-}"
    if [ -z "$variable_value" ]; then
        echo "ERROR: la variable critica $variable_name es obligatoria." >&2
        exit 1
    fi
done

if [ "${APP_ENV:-production}" = "production" ] && [ "${APP_DEBUG:-false}" != "false" ]; then
    echo "ERROR: APP_DEBUG debe ser false en produccion." >&2
    exit 1
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
