<?php

namespace App\Http\Middleware;

use App\Services\AccountMedicationAuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditAccountMedicationChanges
{
    public function __construct(private readonly AccountMedicationAuditService $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $this->audit->recordResponse($request, $response);

        return $response;
    }
}
