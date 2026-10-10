<?php

namespace App\Support;

use App\Http\Controllers\AuthController;
use App\Models\User;
use Illuminate\Http\Request;

final class TokenAbilities
{
    public static function forUser(User $user): array
    {
        $module = match (true) {
            ResourceAccess::admin($user) => 'admin',
            ResourceAccess::professional($user) => 'professional',
            ResourceAccess::family($user) => 'family',
            default => null,
        };

        if ($module === null) {
            return [];
        }

        return ['account:read', 'account:write', 'care:read', 'care:write', $module.':read', $module.':write'];
    }

    public static function requiredFor(Request $request): string
    {
        $route = $request->route();
        $action = $route?->getActionName();
        $uri = $route?->uri() ?? '';
        $module = match (true) {
            in_array($action, [AuthController::class.'@me', AuthController::class.'@updateMe', AuthController::class.'@logout'], true) => 'account',
            in_array('admin', $route?->gatherMiddleware() ?? [], true) => 'admin',
            str_starts_with($uri, 'api/family/') => 'family',
            str_starts_with($uri, 'api/professional/'), str_starts_with($uri, 'api/medications/') => 'professional',
            default => 'care',
        };

        return $module.':'.(in_array($request->method(), ['GET', 'HEAD'], true) ? 'read' : 'write');
    }
}
