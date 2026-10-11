<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BrowserSessions
{
    public function handle(Request $request, Closure $next): Response
    {
        $browser = $request->header('X-Cuidado-Session') === '1'
            || $request->cookies->has(config('session.cookie'))
            || $request->headers->has('Origin') || $request->headers->has('Referer')
            || $request->is('api/csrf-cookie');
        if (! $browser) {
            return $next($request);
        }

        if (config('session.driver') === 'cookie') {
            throw new \RuntimeException('Las sesiones del navegador requieren almacenamiento en el servidor.');
        }
        $request->attributes->set('browser_session', true);
        // A cookie request must never fall back to Bearer or bypass CSRF using that header.
        $request->headers->remove('Authorization');
        $guards = config('sanctum.guard');
        config(['sanctum.guard' => ['web']]);
        Auth::guard('web')->forgetUser();
        Auth::forgetGuards();
        try {
            return app(Pipeline::class)->send($request)->through([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                EnforceBrowserSession::class,
                BrowserCsrf::class,
            ])->then($next);
        } finally {
            config(['sanctum.guard' => $guards]);
        }
    }
}
