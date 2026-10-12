<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\TokenMismatchException;

class BrowserCsrf extends ValidateCsrfToken
{
    protected function runningUnitTests(): bool
    {
        // Browser security is exercised in HTTP tests, never silently disabled.
        return false;
    }

    public function handle($request, Closure $next)
    {
        $origin = $request->header('Origin');
        $allowed = array_merge(config('cors.allowed_origins', []), [$request->getSchemeAndHttpHost()]);
        if (! $this->isReading($request) && $origin !== null && ! in_array($origin, $allowed, true)) {
            throw new TokenMismatchException;
        }

        // Keep token verification mandatory, including Laravel versions that accept Sec-Fetch-Site.
        if (! $this->isReading($request) && ! $this->tokensMatch($request)) {
            throw new TokenMismatchException;
        }

        return parent::handle($request, $next);
    }
}
