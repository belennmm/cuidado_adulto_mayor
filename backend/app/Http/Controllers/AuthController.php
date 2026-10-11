<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthRequest;
use App\Http\Requests\EmptyInputRequest;
use App\Http\Requests\RevokeSessionsRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use App\Support\AccountSecurityState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(AuthRequest $request): JsonResponse
    {
        $data = $request->validated();
        $authentication = $this->authService->authenticate(
            $data['email'],
            $data['password'],
            $request->ip() ?? 'unknown',
            ! $request->attributes->get('browser_session', false)
        );

        if ($request->attributes->get('browser_session')) {
            Auth::guard('web')->login($authentication['user']);
            $request->session()->regenerate(true);
            $request->session()->put([
                'auth_fingerprint' => AccountSecurityState::fingerprint($authentication['user']),
                'auth_last_activity' => now()->timestamp,
                'auth_expires_at' => now()->addMinutes((int) config('session.absolute_timeout', 60))->timestamp,
            ]);

            return response()->json([
                'message' => 'Login exitoso',
                'user' => $this->resource($authentication['user'], $request),
            ]);
        }

        return response()->json([
            'message' => 'Login exitoso',
            'token' => $authentication['token'],
            'user' => $this->resource($authentication['user'], $request),
        ]);
    }

    public function csrfCookie(EmptyInputRequest $request): JsonResponse
    {
        return response()->json(['message' => 'Proteccion CSRF inicializada.']);
    }

    public function register(AuthRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated());

        return response()->json([
            'message' => 'Registro enviado. Un administrador debe aprobar tu cuenta antes de iniciar sesion.',
            'user' => $this->resource($user, $request),
        ], 201);
    }

    public function logout(EmptyInputRequest $request): JsonResponse
    {
        if ($request->attributes->get('browser_session')) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } else {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Logout exitoso']);
    }

    public function logoutAll(RevokeSessionsRequest $request): JsonResponse
    {
        $this->authService->revokeAllSessions($request->user(), $request->validated('current_password'));
        if ($request->attributes->get('browser_session')) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Todas las sesiones fueron cerradas. Inicia sesion nuevamente.']);
    }

    public function me(EmptyInputRequest $request): JsonResponse
    {
        return response()->json([
            'user' => $this->resource($request->user(), $request),
        ]);
    }

    public function updateMe(AuthRequest $request): JsonResponse
    {
        $user = $this->authService->updateProfile($request->user(), $request->validated());

        return response()->json([
            'message' => 'Perfil actualizado correctamente.',
            'user' => $this->resource($user, $request),
        ]);
    }

    private function resource(User $user, Request $request): array
    {
        return UserResource::make($user)->toArray($request);
    }
}
