<?php

namespace App\Http\Controllers;

use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'user' => $this->formatUser($user),
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
            'user' => $this->formatUser($user),
        ], 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout exitoso',
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $this->formatUser($request->user()),
        ]);
    }

    public function updateMe(Request $request)
    {
        $user = $request->user();
        $previousName = $user->name;

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'location' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'birthdate' => 'nullable|date',
            'current_password' => 'nullable|string',
            'new_password' => 'nullable|string|min:8|confirmed',
        ]);

        if (!empty($data['new_password'])) {
            if (!Hash::check((string) ($data['current_password'] ?? ''), $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['La contrasena actual no coincide.'],
                ]);
            }

            $data['password'] = Hash::make($data['new_password']);
        }

        unset($data['current_password'], $data['new_password'], $data['new_password_confirmation']);

        $user->update($data);

        if ($this->isFamilyRole($user->role) && $previousName !== $user->name) {
            OlderAdult::query()
                ->where(function ($query) use ($user, $previousName) {
                    $query
                        ->where('family_caregiver_id', $user->id)
                        ->orWhere(function ($legacyQuery) use ($previousName) {
                            $legacyQuery
                                ->whereNull('family_caregiver_id')
                                ->whereRaw('LOWER(caregiver_family) = ?', [Str::lower((string) $previousName)]);
                        });
                })
                ->update(['caregiver_family' => $user->name]);
        }

        return response()->json([
            'message' => 'Perfil actualizado correctamente.',
            'user' => $this->formatUser($user->refresh()),
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

    private function isFamilyRole(?string $role): bool
    {
        $normalized = strtolower(trim((string) $role));

        return $normalized === 'familiar' || $normalized === 'cuidador_familiar';
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_approved' => $user->is_approved,
            'location' => $user->location,
            'phone' => $user->phone,
            'birthdate' => $user->birthdate?->toDateString(),
        ];
    }
}
