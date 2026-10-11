<?php

namespace App\Http\Middleware;

use App\Logging\RedactSecrets;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectSecretsInUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach (array_keys($request->query->all()) as $key) {
            if (RedactSecrets::sensitive((string) $key)) {
                return response()->json(['message' => 'No envies credenciales en la URL.'], 422);
            }
        }

        return $next($request);
    }
}
