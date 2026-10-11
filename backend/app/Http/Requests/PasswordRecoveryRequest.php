<?php

namespace App\Http\Requests;

use App\Support\PasswordPolicy;

class PasswordRecoveryRequest extends StrictFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = ['email' => ['required', 'string', 'email', 'max:254']];
        if ($this->route()?->getActionMethod() === 'resetPassword') {
            $rules['token'] = ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/i'];
            $rules['password'] = ['required', ...PasswordPolicy::rules(), 'confirmed'];
            $rules['password_confirmation'] = ['required', 'string', 'max:128'];
        }

        return $rules;
    }
}
