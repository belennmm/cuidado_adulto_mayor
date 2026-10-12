<?php

namespace App\Http\Middleware;

use App\Services\SensitiveDataAuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditSensitiveData
{
    public function __construct(private readonly SensitiveDataAuditService $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $this->audit->recordResponse($request, $response);

        return $response;
    }
}
