<?php

namespace App\Http\Middleware;

use App\Support\TokenAbilities;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenScope
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array('auth:sanctum', $request->route()?->gatherMiddleware() ?? [], true)) {
            return $next($request);
        }

        $user = $request->user();
        $required = TokenAbilities::requiredFor($request);
        $token = $user->currentAccessToken();
        // Persisted API credentials have scopes; session authentication still requires current-role permissions.
        if (! in_array($required, TokenAbilities::forUser($user), true)
            || ($token instanceof PersonalAccessToken && $token->exists && ! $token->can($required))) {
            return response()->json(['message' => 'No tienes permiso para realizar esta accion.'], 403);
        }

        return $next($request);
    }
}
