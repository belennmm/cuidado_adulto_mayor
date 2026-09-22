<?php

namespace App\Services;

use App\Models\MedicationAdministration;
use App\Models\OlderAdultMedication;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MedicationStatisticsService
{
    public function statistics(string $filter): array
    {
        $today = Carbon::now((string) config('app.timezone'))->startOfDay();
        [$startDate, $endDate] = $this->periodRange($filter, $today);

        $administrations = MedicationAdministration::query()
            ->with(['medication:id,name', 'olderAdult:id,full_name'])
            ->whereBetween('administration_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('administration_date')
            ->orderBy('administration_time')
            ->get();

        $items = $administrations
            ->groupBy('medication_id')
            ->map(fn (Collection $records) => $this->medicationItem($records, $filter))
            ->sortByDesc('totalUses')
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
        $patients = $records->pluck('older_adult_id')->filter()->unique()->count();
        $activeDays = $records
            ->pluck('administration_date')
            ->map(fn ($date) => $date instanceof Carbon
                ? $date->toDateString()
                : Carbon::parse($date)->toDateString())
            ->unique()
            ->count();

        return [
            'id' => (string) ($first?->medication_id ?? $name),
            'name' => $name,
            'totalUses' => $records->count(),
            'patients' => $patients,
            'streak' => $activeDays,
            'streakLabel' => $activeDays === 1 ? '1 dia con registro' : "{$activeDays} dias con registro",
            'usageLabel' => $this->usageLabel($records->count(), $filter),
            'chartTitle' => $this->chartTitle($filter),
            'rankingNote' => $patients === 1 ? '1 paciente registrado' : "{$patients} pacientes registrados",
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

    private function usageLabel(int $total, string $filter): string
    {
        $period = match ($filter) {
            'month' => 'este mes',
            'year' => 'este ano',
            default => 'hoy',
        };

        return $total === 1 ? "1 administracion {$period}" : "{$total} administraciones {$period}";
    }

    private function chartTitle(string $filter): string
    {
        return match ($filter) {
            'month' => 'Uso semanal del mes',
            'year' => 'Uso mensual del ano',
            default => 'Uso por hora del dia',
        };
    }

    private function buildChart(Collection $records, string $filter): array
    {
        if ($filter === 'year') {
            $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            $buckets = array_fill(1, 12, 0);

            foreach ($records as $record) {
                $buckets[Carbon::parse($record->administration_date)->month] += 1;
            }

            return collect($labels)
                ->map(fn ($label, $index) => ['label' => $label, 'value' => $buckets[$index + 1]])
                ->all();
        }

        if ($filter === 'month') {
            $buckets = array_fill(1, 5, 0);

            foreach ($records as $record) {
                $week = min(5, (int) ceil(Carbon::parse($record->administration_date)->day / 7));
                $buckets[$week] += 1;
            }

            return collect($buckets)
                ->map(fn ($value, $week) => ['label' => "Sem {$week}", 'value' => $value])
                ->values()
                ->all();
        }

        $buckets = array_fill(0, 8, 0);

        foreach ($records as $record) {
            $bucket = min(7, intdiv(Carbon::parse((string) $record->administration_time)->hour, 3));
            $buckets[$bucket] += 1;
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
                    'assigned_patients' => 1,
                    'active_assignments' => $inventoryItem->is_active ? 1 : 0,
                    'administrations_count' => (int) $inventoryItem->administrations_count,
                ];
            })
            ->values()
            ->all();
    }
}
