<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminUserRequest;
use App\Http\Requests\EmptyInputRequest;
use App\Http\Requests\ListRequest;
use App\Support\ListPage;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use App\Services\AdminUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function __construct(private readonly AdminUserService $userService) {}

    public function index(ListRequest $request): JsonResponse
    {
        return $this->collectionResponse($this->userService->all($request->validated()), $request);
    }

    public function professionalCaregivers(ListRequest $request): JsonResponse
    {
        return $this->collectionResponse(
            $this->userService->approvedCaregivers('profesional', $request->validated()), $request,
        );
    }

    public function familyCaregivers(ListRequest $request): JsonResponse
    {
        return $this->collectionResponse(
            $this->userService->approvedCaregivers('familiar', $request->validated()), $request,
        );
    }

    public function store(AdminUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated());

        return $this->userResponse('Usuario creado correctamente.', $user, $request, 201);
    }

    public function show(EmptyInputRequest $request, User $user): JsonResponse
    {
        return response()->json(['user' => $this->resource($user, $request)]);
    }

    public function update(AdminUserRequest $request, User $user): JsonResponse
    {
        $user = $this->userService->update($user, $request->validated());

        return $this->userResponse('Usuario actualizado correctamente.', $user, $request);
    }

    public function approve(EmptyInputRequest $request, User $user): JsonResponse
    {
        $user = $this->userService->approve($user);

        return $this->userResponse('Usuario aprobado correctamente.', $user, $request);
    }

    public function reject(EmptyInputRequest $request, User $user): JsonResponse
    {
        $this->userService->reject($user);

        return response()->json(['message' => 'Solicitud rechazada correctamente.']);
    }

    public function destroy(EmptyInputRequest $request, User $user): JsonResponse
    {
        $this->userService->delete($user);

        return response()->json(['message' => 'Usuario eliminado correctamente.']);
    }

    private function collectionResponse(LengthAwarePaginator $users, Request $request): JsonResponse
    {
        return response()->json(ListPage::payload($users, 'users', fn (User $user) => $this->resource($user, $request)));
    }

    private function userResponse(
        string $message,
        User $user,
        Request $request,
        int $status = 200,
    ): JsonResponse {
        request()->attributes->set('changed_account_fields', array_values(array_filter(
            ['role', 'is_approved', 'password'],
            fn (string $field) => $user->wasChanged($field),
        )));

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
