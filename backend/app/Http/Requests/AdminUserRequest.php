<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminUserRequest extends StrictFormRequest
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
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'string', 'email', 'max:254', Rule::unique('users', 'email')->ignore($user instanceof User ? $user->id : null)],
            'password' => [$updating ? 'nullable' : 'required', 'string', 'max:1024', Password::min(12)->mixedCase()->letters()->numbers()->symbols()],
            'role' => ['required', Rule::in(['admin', 'familiar', 'profesional', 'cuidador_familiar', 'cuidador_profesional'])],
            'location' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:255'], 'birthdate' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:1800-01-01'],
        ];
        if ($updating) {
            $rules['is_approved'] = ['required', 'boolean'];
        }

        return $rules;
    }
}
