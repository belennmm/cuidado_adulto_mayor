<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'login' => ['email' => ['required', 'email'], 'password' => ['required']],
            'register' => ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'unique:users'], 'password' => ['required', 'min:8'], 'role' => ['nullable', 'in:familiar,profesional,cuidador_familiar,cuidador_profesional'], 'location' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:255'], 'birthdate' => ['nullable', 'date']],
            default => ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->user()?->id)], 'location' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:255'], 'birthdate' => ['nullable', 'date'], 'current_password' => ['nullable', 'string'], 'new_password' => ['nullable', 'string', 'min:8', 'confirmed']],
        };
    }
}
