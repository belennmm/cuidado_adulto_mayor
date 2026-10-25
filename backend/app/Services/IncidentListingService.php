<?php

namespace App\Services;

use App\Authorization\IncidentListingAccess;
use App\Models\User;
use App\Queries\IncidentListingQuery;
use App\Support\ListPage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class IncidentListingService
{
    public function __construct(
        private readonly IncidentListingAccess $access,
        private readonly IncidentListingQuery $query,
    ) {}

    public function forDate(User $user, string $date, ?array $input = null): Collection|LengthAwarePaginator
    {
        $role = $this->access->assertAllowed($user);
        $query = $this->query->forDate($user, $role, $date);

        return $input === null ? $query->get() : ListPage::query($query, $input);
    }
}
