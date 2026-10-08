<?php

namespace App\Services;

use App\Models\MedicationAcquisition;
use App\Models\OlderAdultMedication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MedicationStatisticsService
{
    public function statistics(string $filter): array
    {
        $today = Carbon::now((string) config('app.timezone'))->startOfDay();
        [$startDate, $endDate] = $this->periodRange($filter, $today);

        $acquisitions = MedicationAcquisition::query()
            ->with('medication:id,name')
            ->whereBetween('acquired_at', [$startDate->toDateTimeString(), $endDate->toDateTimeString()])
            ->orderBy('acquired_at')
            ->get();

        $items = $acquisitions
            ->groupBy('medication_id')
            ->map(fn (Collection $records) => $this->medicationItem($records, $filter))
            ->sortByDesc('unitsAcquired')
            ->values()
            ->all();

        return [
            'filter' => $filter,
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'items' => $items,
            'inventory' => $this->medicationInventory(),
        ];
    }

    private function medicationItem(Collection $records, string $filter): array
    {
        $first = $records->first();
        $name = $first?->medication?->name ?? 'Medicamento';
        $unitsAcquired = (int) $records->sum('quantity');
        $acquisitionsCount = $records->count();

        return [
            'id' => (string) ($first?->medication_id ?? $name),
            'name' => $name,
            'unitsAcquired' => $unitsAcquired,
            'acquisitionsCount' => $acquisitionsCount,
            'acquisitionLabel' => $this->acquisitionLabel($unitsAcquired, $filter),
            'chartTitle' => $this->chartTitle($filter),
            'rankingNote' => $acquisitionsCount === 1 ? '1 adquisición' : "{$acquisitionsCount} adquisiciones",
            'chart' => $this->buildChart($records, $filter),
        ];
    }

    private function periodRange(string $filter, Carbon $today): array
    {
        return match ($filter) {
            'month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
            'year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            default => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
        };
    }

    private function acquisitionLabel(int $units, string $filter): string
    {
        $period = match ($filter) {
            'month' => 'este mes',
            'year' => 'este año',
            default => 'hoy',
        };

        return $units === 1
            ? "1 unidad adquirida {$period}"
            : "{$units} unidades adquiridas {$period}";
    }

    private function chartTitle(string $filter): string
    {
        return match ($filter) {
            'month' => 'Unidades adquiridas por semana',
            'year' => 'Unidades adquiridas por mes',
            default => 'Unidades adquiridas por hora',
        };
    }

    private function buildChart(Collection $records, string $filter): array
    {
        if ($filter === 'year') {
            $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            $buckets = array_fill(1, 12, 0);

            foreach ($records as $record) {
                $buckets[Carbon::parse($record->acquired_at)->month] += (int) $record->quantity;
            }

            return collect($labels)
                ->map(fn ($label, $index) => ['label' => $label, 'value' => $buckets[$index + 1]])
                ->all();
        }

        if ($filter === 'month') {
            $buckets = array_fill(1, 5, 0);

            foreach ($records as $record) {
                $week = min(5, (int) ceil(Carbon::parse($record->acquired_at)->day / 7));
                $buckets[$week] += (int) $record->quantity;
            }

            return collect($buckets)
                ->map(fn ($value, $week) => ['label' => "Sem {$week}", 'value' => $value])
                ->values()
                ->all();
        }

        $buckets = array_fill(0, 8, 0);

        foreach ($records as $record) {
            $bucket = min(7, intdiv(Carbon::parse($record->acquired_at)->hour, 3));
            $buckets[$bucket] += (int) $record->quantity;
        }

        return collect($buckets)
            ->map(fn ($value, $bucket) => [
                'label' => str_pad((string) ($bucket * 3), 2, '0', STR_PAD_LEFT).':00',
                'value' => $value,
            ])
            ->values()
            ->all();
    }

    private function medicationInventory(): array
    {
        return OlderAdultMedication::query()
            ->with(['medication:id,name', 'olderAdult:id,full_name'])
            ->withCount('administrations')
            ->orderBy('older_adult_id')
            ->orderBy('medication_id')
            ->get()
            ->map(function (OlderAdultMedication $inventoryItem) {
                $status = $inventoryItem->inventoryStatus();

                return [
                    'id' => $inventoryItem->id,
                    'medication_id' => $inventoryItem->medication_id,
                    'older_adult_id' => $inventoryItem->older_adult_id,
                    'older_adult_name' => $inventoryItem->olderAdult?->full_name,
                    'name' => $inventoryItem->medication?->name,
                    'presentation' => $inventoryItem->presentation,
                    'quantity' => (int) $inventoryItem->quantity,
                    'unit' => $inventoryItem->unit,
                    'minimum_stock' => (int) $inventoryItem->minimum_stock,
                    'expiration_date' => $inventoryItem->expiration_date?->toDateString(),
                    'is_active' => (bool) $inventoryItem->is_active,
                    'status' => $status['key'],
                    'status_label' => $status['label'],
                    'assigned_patients' => $inventoryItem->older_adult_id === null ? 0 : 1,
                    'active_assignments' => $inventoryItem->older_adult_id !== null && $inventoryItem->is_active ? 1 : 0,
                    'administrations_count' => (int) $inventoryItem->administrations_count,
                ];
            })
            ->values()
            ->all();
    }
}
