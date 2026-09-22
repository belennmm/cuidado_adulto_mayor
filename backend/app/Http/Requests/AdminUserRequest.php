<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'password' => [$updating ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'familiar', 'profesional', 'cuidador_familiar', 'cuidador_profesional'])],
            'location' => ['nullable', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:255'], 'birthdate' => ['nullable', 'date'],
        ];
        if ($updating) {
            $rules['is_approved'] = ['required', 'boolean'];
        }

        return $rules;
    }
}
