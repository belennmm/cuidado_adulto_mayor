<?php

namespace App\Http\Middleware;

use App\Services\AdministrativeAuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditAdministrativeActions
{
    public function __construct(private readonly AdministrativeAuditService $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $this->audit->recordResponse($request, $response);

        return $response;
    }
}
