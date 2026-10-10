<?php

namespace App\Http\Requests;

use App\Support\ListPage;

class DateFilterRequest extends StrictFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [...ListPage::rules(), 'date' => ['nullable', 'date_format:Y-m-d']];
    }
}
