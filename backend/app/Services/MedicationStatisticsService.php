<?php

namespace App\Services;

use App\Queries\MedicationStatisticsQuery;
use App\Support\ListPage;
use App\Transformers\MedicationStatisticsTransformer;
use App\Validation\MedicationStatisticsFilter;
use Illuminate\Support\Carbon;

class MedicationStatisticsService
{
    public const FILTERS = MedicationStatisticsFilter::VALUES;

    public function __construct(
        private readonly MedicationStatisticsQuery $query,
        private readonly MedicationStatisticsFilter $filterValidator,
        private readonly MedicationStatisticsTransformer $transformer,
    ) {}

    public function statistics(string $filter, array $input = []): array
    {
        $this->filterValidator->assertValid($filter);
        $today = Carbon::now((string) config('app.timezone'))->startOfDay();
        [$startDate, $endDate] = $this->periodRange($filter, $today);
        $acquisitions = $this->query->acquisitionsForPeriod($startDate, $endDate);
        $inventory = ListPage::query($this->query->inventory(), $input);

        return $this->transformer->transform($acquisitions, $inventory, $filter, $startDate, $endDate, $input);
    }

    private function periodRange(string $filter, Carbon $today): array
    {
        return match ($filter) {
            'month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            default => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
        };
    }

}
