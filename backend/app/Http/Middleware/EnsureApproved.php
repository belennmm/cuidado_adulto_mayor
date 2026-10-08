<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApproved
{
    public function handle(Request $request, Closure $next, string $context = 'account'): Response
    {
        if (! $request->user()?->is_approved) {
            $message = match ($context) {
                'family' => 'Esta informacion solo esta disponible para cuidadores familiares.',
                'professional' => 'Esta informacion solo esta disponible para cuidadores profesionales aprobados.',
                default => 'Tu cuenta debe estar aprobada para acceder a este recurso.',
            };

            return response()->json([
                'message' => $message,
            ], 403);
        }

        return $next($request);
    }
}
