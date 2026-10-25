<?php

namespace App\Http\Middleware;

use App\Support\ApiErrorResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeApiErrors
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        return $request->is('api/*') ? ApiErrorResponse::normalize($response) : $response;
    }
}
