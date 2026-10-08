<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $updating = $this->route()?->getActionMethod() === 'update';
        $rules = [
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user instanceof User ? $user->id : null)],
            'password' => [$updating ? 'nullable' : 'required', Password::min(12)->mixedCase()->letters()->numbers()->symbols()],
            'role' => ['required', Rule::in(UserRole::acceptedValues())],
            'location' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:255'], 'birthdate' => ['nullable', 'date'],
        ];
        if ($updating) {
            $rules['is_approved'] = ['required', 'boolean'];
        }

        return $rules;
    }
}
