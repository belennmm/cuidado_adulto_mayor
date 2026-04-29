<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->select('id', 'name', 'email', 'role', 'is_approved', 'location', 'phone', 'birthdate', 'created_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['users' => $users]);
    }

    public function professionalCaregivers(): JsonResponse
    {
        $users = User::query()
            ->select('id', 'name', 'email', 'role', 'is_approved')
            ->where('role', 'profesional')
            ->where('is_approved', true)
            ->orderBy('name')
            ->get();

        return response()->json(['users' => $users]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => $user->only('id', 'name', 'email', 'role', 'is_approved', 'location', 'phone', 'birthdate'),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'familiar', 'profesional', 'cuidador_familiar', 'cuidador_profesional'])],
            'is_approved' => ['required', 'boolean'],
            'location' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'birthdate' => ['nullable', 'date'],
        ]);

        $validated['role'] = $this->normalizeRole($validated['role']);

        if ($validated['role'] === 'admin') {
            $validated['is_approved'] = true;
        }

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'user' => $user->only('id', 'name', 'email', 'role', 'is_approved', 'location', 'phone', 'birthdate'),
        ]);
    }

    public function approve(User $user): JsonResponse
    {
        $user->update(['is_approved' => true]);

        return response()->json([
            'message' => 'Usuario aprobado correctamente.',
            'user' => $user->only('id', 'name', 'email', 'role', 'is_approved'),
        ]);
    }

    public function reject(User $user): JsonResponse
    {
        if ($user->role === 'admin' || (bool) $user->is_approved) {
            return response()->json([
                'message' => 'Solo se pueden rechazar solicitudes pendientes.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'Solicitud rechazada correctamente.',
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado correctamente.',
        ]);
    }

    private function normalizeRole(string $role): string
    {
        return match ($role) {
            'cuidador_profesional' => 'profesional',
            'cuidador_familiar' => 'familiar',
            default => $role,
        };
    }
}
