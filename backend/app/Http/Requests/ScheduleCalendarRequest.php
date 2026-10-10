<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Carbon;

class ScheduleCalendarRequest extends StrictFormRequest
{
    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);
        $validator->after(function (Validator $validator) {
            if (! $validator->errors()->has('start_date') && ! $validator->errors()->has('end_date')
                && Carbon::parse($this->input('start_date'))->diffInDays(Carbon::parse($this->input('end_date'))) > 366) {
                $validator->errors()->add('end_date', 'El calendario permite consultar hasta 366 dias por solicitud.');
            }
        });
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }
}
