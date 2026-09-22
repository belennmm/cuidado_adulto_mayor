<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(AuthRequest $request): JsonResponse
    {
        $data = $request->validated();
        $authentication = $this->authService->authenticate($data['email'], $data['password']);

        return response()->json([
            'message' => 'Login exitoso',
            'token' => $authentication['token'],
            'user' => $this->resource($authentication['user'], $request),
        ]);
    }

    public function register(AuthRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated());

        return response()->json([
            'message' => 'Registro enviado. Un administrador debe aprobar tu cuenta antes de iniciar sesion.',
            'user' => $this->resource($user, $request),
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout exitoso']);
    }

    public function me(Request $request): JsonResponse
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
