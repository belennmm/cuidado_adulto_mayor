<?php

namespace App\Http\Middleware;

use App\Services\SecuritySignalAuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditSecuritySignals
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        app(SecuritySignalAuditService::class)->recordResponse($request, $response);

        return $response;
    }
}
