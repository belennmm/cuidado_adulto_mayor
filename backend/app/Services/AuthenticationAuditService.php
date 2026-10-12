<?php

namespace App\Services;

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticationAuditService
{
    public function __construct(private readonly SecurityEventRecorder $recorder) {}

    public function recordResponse(Request $request, Response $response): void
    {
        if (! $request->is('api/*') || $request->attributes->get('authentication_audited')) {
            return;
        }

        $status = $response->getStatusCode();
        $controllerAction = $request->route()?->getActionName();
        $login = $controllerAction === AuthController::class.'@login';
        $logout = $controllerAction === AuthController::class.'@logout';
        $success = $status >= 200 && $status < 300;

        if ($login && ($success || in_array($status, [401, 403, 429], true))) {
            $event = 'authentication.login';
            $action = 'login';
        } elseif ($logout && $success) {
            $event = 'authentication.logout';
            $action = 'logout';
        } elseif (in_array($status, [401, 403, 429], true)) {
            $event = match ($status) {
                401 => 'authentication.required',
                403 => 'authorization.denied',
                429 => 'security.rate_limit',
            };
            $action = 'access';
        } else {
            return;
        }

        $actorId = $login
            ? $request->attributes->get('authenticated_actor_id')
            : $request->user()?->getAuthIdentifier();

        $this->recorder->record(
            request: $request,
            event: $event,
            action: $action,
            result: $success ? 'success' : ($status === 429 ? 'blocked' : 'denied'),
            actorId: $actorId === null ? null : (int) $actorId,
            resourceType: 'route',
            resourceId: $request->route()?->uri(),
        );

        $request->attributes->set('authentication_audited', true);
    }
}
