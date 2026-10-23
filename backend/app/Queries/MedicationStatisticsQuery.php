<?php

namespace App\Queries;

use App\Models\MedicationAcquisition;
use App\Models\OlderAdultMedication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class MedicationStatisticsQuery
{
    public function acquisitionsForPeriod(Carbon $startDate, Carbon $endDate): Collection
    {
        return MedicationAcquisition::query()
            ->with('medication:id,name')
            ->whereBetween('acquired_at', [$startDate->toDateTimeString(), $endDate->toDateTimeString()])
            ->orderBy('acquired_at')
            ->get();
    }

    public function inventory(): Builder
    {
        return OlderAdultMedication::query()
            ->with(['medication:id,name', 'olderAdult:id,full_name'])
            ->withCount('administrations')
            ->orderBy('older_adult_id')
            ->orderBy('medication_id')->orderBy('id');
    }
}
