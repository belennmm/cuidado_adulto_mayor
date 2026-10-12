<?php

namespace App\Http\Requests;

class RevokeSessionsRequest extends StrictFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['current_password' => ['required', 'string', 'max:1024']];
    }
}
