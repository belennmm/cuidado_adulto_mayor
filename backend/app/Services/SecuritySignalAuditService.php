<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class SecuritySignalAuditService
{
    public function recordResponse(Request $request, Response $response): void
    {
        if (! $request->is('api/*') || $request->attributes->get('security_signals_audited')) {
            return;
        }
        $request->attributes->set('security_signals_audited', true);
        $status = $response->getStatusCode();
        if ($status === 404) {
            $this->record($request, 'resource.not_found', 'access', 'denied');
        }
        if ($status >= 500) {
            app(SecurityInfrastructureAudit::class)->record(
                $request->is('api/login', 'api/mfa/*', 'api/forgot-password', 'api/reset-password') ? 'authentication' : 'application',
            );
        }
        if ($status < 200 || $status >= 300 || ! in_array($request->method(), ['GET', 'HEAD'], true)
            || ! $request->user() || ! ($request->attributes->get('sensitive_data_audited')
                || $request->attributes->get('administrative_audited'))) {
            return;
        }
        $bytes = $response instanceof BinaryFileResponse ? $response->getFile()->getSize() : strlen($response->getContent() ?: '');
        $rows = 0;
        if ($response instanceof JsonResponse) {
            foreach ($response->getData(true) as $value) {
                if (is_array($value) && array_is_list($value)) {
                    $rows += count($value);
                }
            }
        }
        if ($bytes >= (int) config('security_audit.large_response_bytes', 1048576)
            || $rows >= (int) config('security_audit.large_response_rows', 100)) {
            $this->record($request, 'sensitive_data.large_response', 'read', 'success');
        }
    }

    private function record(Request $request, string $event, string $action, string $result): void
    {
        app(SecurityEventRecorder::class)->record(
            $request, $event, $action, $result, $request->user()?->getAuthIdentifier(), 'route', $request->route()?->uri(),
        );
    }
}
