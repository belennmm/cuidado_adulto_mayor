<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AuthRequest extends StrictFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'login' => ['email' => ['required', 'string', 'email', 'max:254'], 'password' => ['required', 'string', 'max:1024']],
            'register' => ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'string', 'email', 'max:254', 'unique:users'], 'password' => ['required', 'string', 'max:1024', Password::min(12)->mixedCase()->letters()->numbers()->symbols()], 'role' => ['nullable', 'in:familiar,profesional,cuidador_familiar,cuidador_profesional'], 'location' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:255'], 'birthdate' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:1800-01-01'], 'privacy_consent' => ['required', 'accepted']],
            default => ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'string', 'email', 'max:254', Rule::unique('users', 'email')->ignore($this->user()?->id)], 'location' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:255'], 'birthdate' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:1800-01-01'], 'current_password' => ['nullable', 'string', 'max:1024'], 'new_password_confirmation' => ['nullable', 'string', 'max:1024'], 'new_password' => ['nullable', 'string', 'max:1024', Password::min(12)->mixedCase()->letters()->numbers()->symbols(), 'confirmed']],
        };
    }
}
