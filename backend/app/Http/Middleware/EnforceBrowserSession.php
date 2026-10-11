<?php

namespace App\Http\Middleware;

use App\Support\AccountSecurityState;
use App\Support\ResourceAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceBrowserSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        $user = $guard->user();
        if ($user) {
            $current = $user->fresh();
            $last = $request->session()->get('auth_last_activity');
            $expires = $request->session()->get('auth_expires_at');
            $fingerprint = $request->session()->get('auth_fingerprint');
            $valid = $current && (ResourceAccess::admin($current) || ResourceAccess::caregiver($current))
                && is_int($last) && is_int($expires) && is_string($fingerprint)
                && now()->timestamp - $last < (int) config('session.idle_timeout', 15) * 60
                && now()->timestamp < $expires
                && hash_equals(AccountSecurityState::fingerprint($current), $fingerprint);
            if (! $valid) {
                $guard->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return response()->json(['message' => 'La sesion expiro. Inicia sesion nuevamente.'], 401);
            }
            $guard->setUser($current);
            $request->session()->put('auth_last_activity', now()->timestamp);
        }

        $response = $next($request);
        // A password or privilege change during this request must not re-save an authenticated session.
        if ($user && $guard->check()) {
            $current = $guard->user()->fresh();
            if (! $current || ! hash_equals(AccountSecurityState::fingerprint($current), (string) $request->session()->get('auth_fingerprint'))) {
                $guard->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        return $response;
    }
}
