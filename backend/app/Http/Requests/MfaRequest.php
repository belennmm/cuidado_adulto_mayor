<?php

namespace App\Http\Requests;

class MfaRequest extends StrictFormRequest
{
    public function rules(): array
    {
        if ($this->route()?->getActionMethod() === 'verifyMfa') {
            return ['challenge' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'], 'code' => ['required', 'string', 'regex:/\A(?:[0-9]{6}|[a-f0-9]{32})\z/']];
        }

        return ['current_password' => ['required', 'string', 'max:1024'], 'code' => [$this->route()?->getActionMethod() === 'setupMfa' ? 'nullable' : 'required', 'string', 'regex:/\A(?:[0-9]{6}|[a-f0-9]{32})\z/']];
    }
}
