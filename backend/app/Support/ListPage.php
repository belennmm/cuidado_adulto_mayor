<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ListPage
{
    public static function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    public static function query(Builder $query, array $input): LengthAwarePaginator
    {
        return $query->paginate((int) ($input['per_page'] ?? 25), ['*'], 'page', (int) ($input['page'] ?? 1));
    }

    public static function collection(Collection $items, array $input): LengthAwarePaginator
    {
        $perPage = (int) ($input['per_page'] ?? 25);
        $page = (int) ($input['page'] ?? 1);

        return new LengthAwarePaginator($items->slice(($page - 1) * $perPage, $perPage)->values(), $items->count(), $perPage, $page);
    }

    public static function payload(LengthAwarePaginator $page, string $key, ?callable $format = null): array
    {
        $items = $page->getCollection();

        return [
            $key => ($format ? $items->map($format) : $items)->values(),
            'pagination' => [
                'current_page' => $page->currentPage(), 'per_page' => $page->perPage(),
                'total' => $page->total(), 'last_page' => $page->lastPage(),
            ],
        ];
    }
}
