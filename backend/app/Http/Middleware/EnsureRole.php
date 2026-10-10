<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $role = Str::of((string) $user->role)->ascii()->lower()->trim()->toString();
        $role = match ($role) {
            'cuidador_familiar' => 'familiar',
            'cuidador_profesional' => 'profesional',
            default => $role,
        };

        // Only explicitly listed roles may enter; administrators have no implicit bypass.
        if (! in_array($role, $roles, true)) {
            return response()->json(['message' => 'No tienes permiso para realizar esta accion.'], 403);
        }

        if (! $user->is_approved) {
            $message = match ($role) {
                'profesional' => 'Esta informacion solo esta disponible para cuidadores profesionales aprobados.',
                'familiar' => 'Esta informacion solo esta disponible para cuidadores familiares.',
                default => 'Tu cuenta debe estar aprobada para realizar esta accion.',
            };

            return response()->json(['message' => $message], 403);
        }

        return $next($request);
    }
}
