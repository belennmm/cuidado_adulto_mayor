<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AdminUserService;
use App\Services\MedicationStatisticsService;
use App\Services\VacationRequestService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ParameterizedQueriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_raw_name_lookup_binds_sql_metacharacters_as_a_literal_value(): void
    {
        $name = "Familia' OR 1=1 --";
        $family = User::factory()->create(['name' => $name, 'role' => 'familiar', 'is_approved' => true]);
        User::factory()->create(['role' => 'familiar', 'is_approved' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $headers = ['Authorization' => 'Bearer '.$admin->createToken('sql')->plainTextToken];
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries) {
            $queries[] = $query;
        });
        $response = $this->postJson('/api/admin/older-adults', ['full_name' => "O'Connor <em>texto</em>", 'caregiver_family' => $name], $headers)->assertCreated();
        $this->assertDatabaseHas('older_adults', ['id' => $response->json('older_adult.id'), 'family_caregiver_id' => $family->id, 'full_name' => "O'Connor <em>texto</em>"]);
        $lookup = collect($queries)->first(fn ($query) => str_contains($query->sql, 'LOWER(name) = ?'));
        $this->assertNotNull($lookup);
        $this->assertContains(strtolower($name), $lookup->bindings);
        $this->assertStringNotContainsString($name, $lookup->sql);
        $this->assertDatabaseCount('users', 3);
    }

    public function test_raw_priority_order_uses_a_bound_status(): void
    {
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries) {
            $queries[] = $query;
        });
        app(VacationRequestService::class)->allForAdmin();
        $query = collect($queries)->first(fn ($query) => str_contains($query->sql, 'CASE WHEN status = ?'));
        $this->assertNotNull($query);
        $this->assertContains('pending', $query->bindings);
        $this->assertStringNotContainsString("'pending'", $query->sql);
    }

    #[DataProvider('dynamicQueryFields')]
    public function test_http_does_not_accept_client_selected_sql_identifiers(string $path, string $field): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $headers = ['Authorization' => 'Bearer '.$admin->createToken('sql')->plainTextToken];
        $this->getJson($path.'?'.http_build_query([$field => 'name desc; DROP TABLE users; --']), $headers)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('users', 1);
    }

    public static function dynamicQueryFields(): array
    {
        $cases = [];
        foreach (['/api/admin/users', '/api/admin/medications/inventory', '/api/admin/medication-statistics', '/api/incidents', '/api/rutinas', '/api/admin/mobility-exercises'] as $path) {
            foreach (['columns', 'sort', 'order', 'direction', 'where'] as $field) {
                $cases[] = [$path, $field];
            }
        }

        return $cases;
    }

    #[DataProvider('invalidServiceFilters')]
    public function test_services_reject_filters_outside_the_explicit_allowlist(string $service, string $method, string $value): void
    {
        $this->expectException(ValidationException::class);
        app($service)->{$method}($value);
    }

    public static function invalidServiceFilters(): array
    {
        return [
            [MedicationStatisticsService::class, 'statistics', "day' OR 1=1 --"],
            [MedicationStatisticsService::class, 'statistics', 'unknown'],
            [AdminUserService::class, 'approvedCaregivers', 'admin'],
            [AdminUserService::class, 'approvedCaregivers', "familiar' OR 1=1 --"],
        ];
    }
}
