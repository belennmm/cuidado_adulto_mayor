<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasRole(UserRole::ADMIN)) {
            return response()->json([
                'message' => 'Solo un administrador puede realizar esta accion.',
            ], 403);
        }

        return $next($request);
    }
}
