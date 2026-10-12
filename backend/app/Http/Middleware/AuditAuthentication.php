<?php

namespace App\Http\Middleware;

use App\Services\AuthenticationAuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditAuthentication
{
    public function __construct(private readonly AuthenticationAuditService $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $this->audit->recordResponse($request, $response);

        return $response;
    }
}
