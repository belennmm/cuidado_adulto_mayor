<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Validation\Rule;

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
            'password' => [$updating ? 'nullable' : 'required', ...PasswordPolicy::rules($user instanceof User ? $user : null)],
            'role' => ['required', Rule::in(UserRole::acceptedValues())],
            'location' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:255'], 'birthdate' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:1800-01-01'],
        ];
        if ($updating) {
            $rules['is_approved'] = ['required', 'boolean'];
        }

        return $rules;
    }
}
