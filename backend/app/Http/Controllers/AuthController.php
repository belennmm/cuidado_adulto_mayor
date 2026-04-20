<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Credenciales invalidas',
            ], 401);
        }

        if (!$this->canLogin($user)) {
            return response()->json([
                'message' => 'Tu cuenta esta pendiente de aprobacion por un administrador.',
            ], 403);
        }

        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_approved' => $user->is_approved,
            ],
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'role' => 'nullable|in:familiar,profesional,cuidador_familiar,cuidador_profesional',
            'location' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'birthdate' => 'nullable|date',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $this->normalizePublicRole($request->role),
            'is_approved' => false,
            'location' => $request->location,
            'phone' => $request->phone,
            'birthdate' => $request->birthdate,
        ]);

        return response()->json([
            'message' => 'Registro enviado. Un administrador debe aprobar tu cuenta antes de iniciar sesion.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_approved' => $user->is_approved,
            ],
        ], 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout exitoso',
        ]);
    }

    private function normalizePublicRole(?string $role): string
    {
        return match ($role) {
            'profesional', 'cuidador_profesional' => 'profesional',
            'familiar', 'cuidador_familiar' => 'familiar',
            default => 'familiar',
        };
    }

    private function canLogin(User $user): bool
    {
        $role = strtolower(trim((string) $user->role));

        if ($role === 'admin') {
            return true;
        }

        return (bool) $user->is_approved;
    }
}
