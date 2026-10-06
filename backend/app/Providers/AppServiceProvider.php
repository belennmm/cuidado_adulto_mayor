<?php

namespace App\Providers;

use App\Models\Rutina;
use App\Policies\RutinaPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->validateProductionConfiguration();

        Gate::policy(Rutina::class, RutinaPolicy::class);

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);
        RateLimiter::for('registration', fn (Request $request) => [
            Limit::perHour(10)->by($request->ip()),
        ]);
    }

    private function validateProductionConfiguration(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        $required = [
            'APP_KEY' => config('app.key'),
            'APP_URL' => config('app.url'),
            'DB_PASSWORD' => config('database.connections.'.config('database.default').'.password'),
            'CORS_ALLOWED_ORIGINS' => config('cors.allowed_origins'),
        ];
        $missing = array_keys(array_filter($required, fn (mixed $value) => $value === null || $value === '' || $value === []));

        if (config('app.debug')) {
            $missing[] = 'APP_DEBUG debe ser false';
        }

        $origins = config('cors.allowed_origins', []);
        if (array_filter($origins, fn (string $origin) => ! str_starts_with($origin, 'https://'))) {
            $missing[] = 'CORS_ALLOWED_ORIGINS debe usar HTTPS';
        }

        if ($missing !== []) {
            throw new RuntimeException('Configuracion de produccion insegura: '.implode(', ', $missing));
        }
    }
}
