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
        $allowedOrigins = array_filter(array_map(
            'trim',
            explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://127.0.0.1:3000'))
        ));
        $originAllowed = $origin !== null && in_array($origin, $allowedOrigins, true);

        if ($request->isMethod('OPTIONS')) {
            if (! $originAllowed) {
                return response()->json(['message' => 'Origen no autorizado.'], 403);
            }

            $response = response('', 204);
        } else {
            $response = $next($request);
        }

        if ($originAllowed) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept');
            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }
}
