<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminUserRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use App\Services\AdminUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AdminUserController extends Controller
{
    public function __construct(private readonly AdminUserService $userService) {}

    public function index(Request $request): JsonResponse
    {
        return $this->collectionResponse($this->userService->all(), $request);
    }

    public function professionalCaregivers(Request $request): JsonResponse
    {
        return $this->collectionResponse(
            $this->userService->approvedCaregivers('profesional'), $request,
        );
    }

    public function familyCaregivers(Request $request): JsonResponse
    {
        return $this->collectionResponse(
            $this->userService->approvedCaregivers('familiar'), $request,
        );
    }

    public function store(AdminUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated());

        return $this->userResponse('Usuario creado correctamente.', $user, $request, 201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        return response()->json(['user' => $this->resource($user, $request)]);
    }

    public function update(AdminUserRequest $request, User $user): JsonResponse
    {
        $user = $this->userService->update($user, $request->validated());

        return $this->userResponse('Usuario actualizado correctamente.', $user, $request);
    }

    public function approve(Request $request, User $user): JsonResponse
    {
        $user = $this->userService->approve($user);

        return $this->userResponse('Usuario aprobado correctamente.', $user, $request);
    }

    public function reject(User $user): JsonResponse
    {
        $this->userService->reject($user);

        return response()->json(['message' => 'Solicitud rechazada correctamente.']);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(['message' => 'Usuario eliminado correctamente.']);
    }

    private function collectionResponse(Collection $users, Request $request): JsonResponse
    {
        return response()->json([
            'users' => $users->map(fn (User $user) => $this->resource($user, $request))->values(),
        ]);
    }

    private function userResponse(
        string $message,
        User $user,
        Request $request,
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'user' => $this->resource($user, $request),
        ], $status);
    }

    private function resource(User $user, Request $request): array
    {
        return AdminUserResource::make($user)->toArray($request);
    }
}
