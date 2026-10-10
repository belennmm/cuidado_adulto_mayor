<?php

namespace App\Http\Requests;

class MobilityIndexRequest extends StrictFormRequest
{
    public function rules(): array
    {
        return ['active' => ['nullable', 'in:0,1,true,false']];
    }
}
