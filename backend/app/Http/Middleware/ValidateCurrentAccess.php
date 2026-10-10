<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;

class ValidateCurrentAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array('auth:sanctum', $request->route()?->gatherMiddleware() ?? [], true)) {
            return $next($request);
        }

        $authenticated = $request->user();
        $token = $authenticated?->currentAccessToken();
        if ($bearer = $request->bearerToken()) {
            $token = Sanctum::personalAccessTokenModel()::findToken($bearer);
            $authenticated = $token?->tokenable;
        }
        $current = $authenticated instanceof User ? $authenticated->fresh() : null;
        if (! $current) {
            throw new AuthenticationException;
        }

        if ($token instanceof PersonalAccessToken && $token->exists) {
            $token = $token->fresh();
            $expiration = config('sanctum.expiration');
            if (! $token || ! $token->tokenable()->whereKey($current->id)->exists()
                || ($token->expires_at && $token->expires_at->isPast())
                || ($expiration && $token->created_at->lte(now()->subMinutes((int) $expiration)))) {
                throw new AuthenticationException;
            }
        }

        $current->withAccessToken($token);
        Auth::guard('sanctum')->setUser($current);
        $request->setUserResolver(fn () => $current);

        return $next($request);
    }
}
