<?php

namespace Tests\Feature;

use App\Http\Requests\StrictFormRequest;
use App\Models\Medication;
use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionFunction;
use ReflectionMethod;
use Tests\TestCase;

class StrictInputValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_api_action_declares_a_strict_form_request(): void
    {
        $count = 0;
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }
            $action = $route->getAction('uses');
            $reflection = $action instanceof \Closure
                ? new ReflectionFunction($action)
                : new ReflectionMethod(...explode('@', $action));
            $request = $reflection->getParameters()[0] ?? null;
            $this->assertNotNull($request, $route->uri());
            $this->assertTrue(is_subclass_of($request->getType()?->getName() ?? '', StrictFormRequest::class), $route->uri());
            foreach ($route->parameterNames() as $parameter) {
                $this->assertSame('[1-9][0-9]{0,18}', $route->wheres[$parameter] ?? null, $route->uri());
            }
            $count++;
        }
        $this->assertSame(77, $count);
    }

    public function test_nested_unknown_fields_reject_the_entire_write(): void
    {
        $headers = $this->headers();
        $adult = OlderAdult::create(['full_name' => 'Original']);
        $this->putJson('/api/admin/older-adults/'.$adult->id, [
            'full_name' => 'No debe guardarse',
            'medications' => [['name' => 'No guardar', 'recorded_by' => 123, 'metadata' => ['role' => 'admin']]],
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors(['medications.0.recorded_by', 'medications.0.metadata']);
        $this->assertSame('Original', $adult->refresh()->full_name);
        $this->assertDatabaseCount('medications', 0);
    }

    public function test_form_payload_is_subject_to_the_same_unknown_field_rejection(): void
    {
        $headers = $this->headers();
        $this->post('/api/admin/older-adults', ['full_name' => 'Prueba', 'role' => 'admin'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertDatabaseCount('older_adults', 0);
        $this->post('/api/admin/older-adults', ['full_name' => 'Formulario valido'], $headers)->assertCreated();
    }

    #[DataProvider('invalidBodies')]
    public function test_invalid_json_is_rejected_even_on_bodyless_commands(string $body): void
    {
        $headers = $this->headers();
        $this->call('POST', '/api/logout', [], [], [], [
            'HTTP_AUTHORIZATION' => $headers['Authorization'], 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
        ], $body)->assertUnprocessable()->assertJsonValidationErrors('_body');
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public static function invalidBodies(): array
    {
        return [['{"broken":'], ['[1,2]'], ['"text"'], ['null'], ['false']];
    }

    public function test_unparsed_body_cannot_be_silently_ignored_by_a_command(): void
    {
        $headers = $this->headers();
        $this->call('POST', '/api/logout', [], [], [], [
            'HTTP_AUTHORIZATION' => $headers['Authorization'], 'CONTENT_TYPE' => 'text/plain',
        ], 'role=admin')->assertUnprocessable()->assertJsonValidationErrors('_body');
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_query_and_body_channels_cannot_be_used_to_bypass_validation(): void
    {
        $headers = $this->headers();
        $this->getJson('/api/admin/users?role=admin', $headers)->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->postJson('/api/admin/older-adults?full_name=Query', ['full_name' => 'Cuerpo'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('full_name');
        $this->json('GET', '/api/admin/users', ['name' => 'Cuerpo'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('_body');
        $this->assertDatabaseCount('older_adults', 0);
    }

    public function test_bodyless_actions_reject_fields_without_performing_the_action(): void
    {
        $headers = $this->headers();
        $target = User::factory()->create(['role' => 'familiar', 'is_approved' => false]);
        $this->patchJson('/api/admin/users/'.$target->id.'/approve', ['role' => 'admin'], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertFalse($target->refresh()->is_approved);
        $this->deleteJson('/api/admin/users/'.$target->id, ['id' => $target->id], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('id');
        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->postJson('/api/logout', ['token' => 'other'], $headers)->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    #[DataProvider('invalidAdultInputs')]
    public function test_types_dates_ranges_and_collection_limits_are_enforced(array $input, string $error): void
    {
        $this->postJson('/api/admin/older-adults', ['full_name' => 'Valido', ...$input], $this->headers())
            ->assertUnprocessable()->assertJsonValidationErrors($error);
        $this->assertDatabaseCount('older_adults', 0);
    }

    public static function invalidAdultInputs(): array
    {
        return [
            [['full_name' => ['array']], 'full_name'],
            [['full_name' => str_repeat('a', 256)], 'full_name'],
            [['age' => 131], 'age'], [['age' => -1], 'age'], [['age' => '1 OR 1=1'], 'age'],
            [['gender' => 'desconocido'], 'gender'], [['status' => 'inventado'], 'status'],
            [['birthdate' => '2026-02-30'], 'birthdate'], [['birthdate' => '2500-01-01'], 'birthdate'],
            [['medical_history' => str_repeat('a', 10001)], 'medical_history'],
            [['medications' => array_fill(0, 101, ['name' => 'Pastilla'])], 'medications'],
            [['medications' => ['key' => ['name' => 'Pastilla']]], 'medications'],
            [['medications' => [['name' => 'Pastilla', 'quantity' => 4294967296]]], 'medications.0.quantity'],
            [['medications' => [['name' => 'Pastilla', 'days' => array_fill(0, 8, 'lunes')]]], 'medications.0.days'],
            [['medications' => [['name' => 'Pastilla', 'days' => ['inventado']]]], 'medications.0.days.0'],
        ];
    }

    #[DataProvider('invalidWrites')]
    public function test_write_contracts_reject_invalid_values_before_persistence(string $path, string $role, array $body, string $error): void
    {
        $user = User::factory()->create(['role' => $role, 'is_approved' => true]);
        $adult = OlderAdult::create(['full_name' => 'Asignado', 'professional_caregiver_id' => $user->id]);
        if (array_key_exists('older_adult_id', $body)) {
            $body['older_adult_id'] = $adult->id;
        }
        $headers = ['Authorization' => 'Bearer '.$user->createToken('validation')->plainTextToken];
        $this->postJson($path, $body, $headers)->assertUnprocessable()->assertJsonValidationErrors($error);
        foreach (['rutinas', 'routine_notes', 'mobility_exercises', 'incidents', 'caregiver_schedules', 'vacation_requests', 'personal_access_tokens'] as $table) {
            $this->assertDatabaseCount($table, $table === 'personal_access_tokens' ? 1 : 0);
        }
    }

    public static function invalidWrites(): array
    {
        $exercise = ['title' => 'Ejercicio', 'focus' => 'Movilidad', 'duration_minutes' => 5, 'repetitions' => '5', 'instructions' => ['Mover'], 'precaution' => 'Supervision'];
        $routine = ['older_adult_id' => 1, 'nombre' => 'Rutina', 'horario' => '10:00', 'actividades' => ['Caminar']];

        return [
            ['/api/login', 'admin', ['email' => ['malformed'], 'password' => 'password'], 'email'],
            ['/api/login', 'admin', ['email' => 'test@example.com', 'password' => ['array']], 'password'],
            ['/api/login', 'admin', ['email' => 'test@example.com', 'password' => str_repeat('a', 1025)], 'password'],
            ['/api/admin/users', 'admin', ['name' => 'Nuevo', 'email' => 'new@example.com', 'role' => 'admin', 'password' => ['array']], 'password'],
            ['/api/admin/mobility-exercises', 'admin', [...$exercise, 'instructions' => array_fill(0, 21, 'Mover')], 'instructions'],
            ['/api/admin/mobility-exercises', 'admin', [...$exercise, 'slug' => '../path'], 'slug'],
            ['/api/admin/mobility-exercises', 'admin', [...$exercise, 'duration_minutes' => 1441], 'duration_minutes'],
            ['/api/rutinas', 'profesional', [...$routine, 'actividades' => array_fill(0, 101, 'Caminar')], 'actividades'],
            ['/api/rutinas', 'profesional', [...$routine, 'adulto_mayor_id' => 999], 'adulto_mayor_id'],
            ['/api/professional/routine-notes', 'profesional', ['older_adult_id' => 1, 'content' => str_repeat('a', 5001)], 'content'],
            ['/api/professional/incidents', 'profesional', ['older_adult_id' => 1, 'title' => 'Prueba', 'severity' => 'inventada'], 'severity'],
            ['/api/schedules', 'profesional', ['day_of_week' => 7, 'start_time' => '08:00', 'end_time' => '16:00'], 'day_of_week'],
        ];
    }

    public function test_valid_boundary_inputs_and_nested_fields_are_accepted(): void
    {
        $this->postJson('/api/admin/older-adults', [
            'full_name' => str_repeat('a', 255), 'age' => 130, 'birthdate' => '1900-01-01',
            'medical_history' => str_repeat('a', 10000),
            'medications' => [['name' => 'Pastilla', 'quantity' => 0, 'days' => ['lunes', 'martes'], 'notes' => 'Con comida']],
        ], $this->headers())->assertCreated();
        $this->assertDatabaseCount('older_adults', 1);
        $this->assertDatabaseCount('older_adult_medications', 1);
    }

    public function test_inventory_increment_cannot_overflow_the_database_column(): void
    {
        $item = OlderAdultMedication::create([
            'medication_id' => Medication::create(['name' => 'Pastilla'])->id,
            'quantity' => 4294967295, 'presentation' => 'Tableta', 'unit' => 'unidad', 'minimum_stock' => 0,
        ]);
        $this->patchJson('/api/admin/medications/inventory/'.$item->id.'/stock', ['action' => 'increase', 'amount' => 1], $this->headers())
            ->assertUnprocessable();
        $this->assertSame(4294967295, $item->refresh()->quantity);
        $this->assertDatabaseCount('medication_acquisitions', 0);
    }

    public function test_calendar_has_a_bounded_window_and_filters_reject_injection_strings(): void
    {
        $headers = $this->headers();
        $this->getJson('/api/admin/schedules/calendar?start_date=2026-01-01&end_date=2030-01-01', $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('end_date');
        $this->getJson('/api/rutinas?older_adult_id=1%20OR%201=1', $headers)->assertUnprocessable()->assertJsonValidationErrors('older_adult_id');
        $this->getJson('/api/admin/mobility-exercises?active=arbitrary', $headers)->assertUnprocessable()->assertJsonValidationErrors('active');
    }

    public function test_authentication_and_authorization_still_precede_input_validation(): void
    {
        $this->postJson('/api/admin/older-adults', ['role' => 'admin'])->assertUnauthorized();
        $this->postJson('/api/admin/older-adults', ['role' => 'admin'], $this->headers('familiar'))->assertForbidden();
    }

    public function test_route_identifiers_reject_non_decimal_values_and_injection_fragments(): void
    {
        $adult = OlderAdult::create(['full_name' => 'Propio']);
        $headers = $this->headers();
        $this->getJson('/api/admin/older-adults/'.$adult->id, $headers)->assertOk();
        foreach (['0', '-1', '01', '1.0', '1e0', $adult->id.'abc', '1 OR 1=1', str_repeat('9', 20)] as $id) {
            $this->getJson('/api/admin/older-adults/'.rawurlencode($id), $headers)->assertNotFound()
                ->assertExactJson(['message' => 'Recurso no encontrado.']);
        }
        $this->assertDatabaseCount('older_adults', 1);
    }

    private function headers(string $role = 'admin'): array
    {
        $user = User::factory()->create(['role' => $role, 'is_approved' => true]);

        return ['Authorization' => 'Bearer '.$user->createToken('validation')->plainTextToken];
    }
}
