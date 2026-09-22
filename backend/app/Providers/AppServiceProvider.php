<?php

namespace App\Providers;

use App\Models\Rutina;
use App\Policies\RutinaPolicy;
use Illuminate\Support\Facades\Gate;
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
    }
}
