<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (!$this->app->bound('request')) {
            $this->app->instance('request', Request::createFromGlobals());
        }
    }

    public function boot(): void
    {
        //
    }
}