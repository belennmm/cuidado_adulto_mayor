<?php

namespace App\Http\Requests;

class EmptyInputRequest extends StrictFormRequest
{
    public function rules(): array
    {
        return [];
    }
}
