<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleCors
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');
        $allowedOrigins = config('cors.allowed_origins', []);
        $allowedOrigin = in_array($origin, $allowedOrigins, true) ? $origin : null;

        if ($request->isMethod('OPTIONS')) {
            if (! $allowedOrigin) {
                return response('', 204);
            }

            return $this->withCorsHeaders(response('', 204), $allowedOrigin);
        }

        $response = $next($request);

        return $allowedOrigin ? $this->withCorsHeaders($response, $allowedOrigin) : $response;
    }

    private function withCorsHeaders(Response $response, string $origin): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept');
        $response->headers->set('Vary', 'Origin');

        return $response;
    }
}
