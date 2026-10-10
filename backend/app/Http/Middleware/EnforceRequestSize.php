<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceRequestSize
{
    public function handle(Request $request, Closure $next): Response
    {
        $maximumBytes = (int) config('app.max_request_bytes', 2 * 1024 * 1024);
        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);

        if ($contentLength > $maximumBytes || strlen($request->getContent()) > $maximumBytes) {
            return response()->json(['message' => 'La solicitud excede el tamano permitido.'], 413);
        }

        return $next($request);
    }
}
