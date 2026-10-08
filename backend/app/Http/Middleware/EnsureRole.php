<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $allowedRoles = collect($roles)
            ->map(fn (string $role) => UserRole::fromValue($role))
            ->filter()
            ->all();

        if (! $user || ! in_array($user->roleEnum(), $allowedRoles, true)) {
            return response()->json([
                'message' => 'No tienes acceso a este recurso.',
            ], 403);
        }

        return $next($request);
    }
}
