<?php

namespace App\Validation;

use Illuminate\Validation\ValidationException;

final class MedicationStatisticsFilter
{
    public const VALUES = ['day', 'month', 'year'];

    public function assertValid(string $filter): void
    {
        if (! in_array($filter, self::VALUES, true)) {
            throw ValidationException::withMessages(['filter' => ['El filtro debe ser day, month o year.']]);
        }
    }
}
