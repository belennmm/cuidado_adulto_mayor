<?php

namespace App\Providers;

use App\Models\Rutina;
use App\Policies\RutinaPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Rutina::class, RutinaPolicy::class);

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);
        RateLimiter::for('registration', fn (Request $request) => [
            Limit::perHour(10)->by($request->ip()),
        ]);
    }
}
