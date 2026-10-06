<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectDisallowedMethods
{
    private const ALLOWED_METHODS = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getMethod(), self::ALLOWED_METHODS, true)) {
            return response()->json(['message' => 'Metodo HTTP no permitido.'], 405)
                ->header('Allow', implode(', ', self::ALLOWED_METHODS));
        }

        return $next($request);
    }
}
