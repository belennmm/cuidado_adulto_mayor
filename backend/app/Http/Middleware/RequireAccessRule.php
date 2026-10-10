<?php

namespace App\Http\Middleware;

use App\Http\Controllers\AuthController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAccessRule
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $action = $route?->getActionName();

        // These are the only endpoints outside the resource-policy contract.
        if (($route?->getName() === 'api.ping' && in_array($request->method(), ['GET', 'HEAD'], true))
            || in_array($action, [AuthController::class.'@login', AuthController::class.'@register'], true)) {
            return $next($request);
        }

        $middleware = $route?->gatherMiddleware() ?? [];
        $authenticated = in_array('auth:sanctum', $middleware, true);
        $accountAction = in_array($action, [
            AuthController::class.'@me', AuthController::class.'@updateMe', AuthController::class.'@logout',
        ], true);
        $hasPolicy = (bool) array_filter($middleware, fn (string $rule) => str_starts_with($rule, 'can:'));

        if (! $authenticated || (! $accountAction && ! $hasPolicy)) {
            return response()->json(['message' => 'No tienes permiso para realizar esta accion.'], 403);
        }

        return $next($request);
    }
}
