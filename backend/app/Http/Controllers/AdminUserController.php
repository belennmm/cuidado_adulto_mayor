<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;

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
}
