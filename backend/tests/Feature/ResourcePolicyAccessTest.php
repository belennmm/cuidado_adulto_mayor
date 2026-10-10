<?php

namespace Tests\Feature;

use App\Models\CaregiverSchedule;
use App\Models\Incident;
use App\Models\Medication;
use App\Models\MobilityExercise;
use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
use App\Models\RoutineNote;
use App\Models\Rutina;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\CaregiverAccessService;
use App\Services\RoutineService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ResourcePolicyAccessTest extends TestCase
{
    use RefreshDatabase;

    private const RESOURCES = [
        User::class, OlderAdult::class, Incident::class, RoutineNote::class, Rutina::class,
        CaregiverSchedule::class, OlderAdultMedication::class, MobilityExercise::class, VacationRequest::class,
    ];

    public function test_sensitive_models_have_registered_policies_and_undefined_abilities_are_denied_even_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (self::RESOURCES as $model) {
            $this->assertNotNull(Gate::getPolicyFor($model), $model);
            $this->assertFalse(Gate::forUser($admin)->allows('undefinedAbility', $model), $model);
        }

        $this->assertFalse(Gate::forUser($admin)->allows('viewAny', RoutineNote::class));
        $this->assertFalse(Gate::forUser($admin)->allows('create', VacationRequest::class));
    }

    #[DataProvider('deniedUsers')]
    public function test_unknown_roles_and_unapproved_caregivers_are_denied_by_policies_without_route_middleware(string $role, bool $approved): void
    {
        $user = User::factory()->create(['role' => $role, 'is_approved' => $approved]);

        foreach (self::RESOURCES as $model) {
            $this->assertFalse(Gate::forUser($user)->allows('viewAny', $model), $model);
            $this->assertFalse(Gate::forUser($user)->allows('create', $model), $model);
        }
    }

    public static function deniedUsers(): array
    {
        return [['auditor', true], ['', true], ['administrador', true], ['familiar', false], ['cuidador_profesional', false]];
    }

    public function test_api_route_without_an_explicit_policy_is_denied_even_for_admin(): void
    {
        Route::middleware(['api', 'auth:sanctum'])->get('/api/unclassified-resource', fn () => ['secret' => 'protected']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_approved' => true]));

        $this->getJson('/api/unclassified-resource')->assertForbidden()->assertJsonMissing(['secret' => 'protected']);
    }

    public function test_an_explicit_but_undefined_ability_does_not_allow_an_api_route(): void
    {
        Route::middleware(['api', 'auth:sanctum'])->get('/api/undefined-permission', fn () => ['secret' => 'protected'])
            ->can('undefinedAbility', Rutina::class);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_approved' => true]));

        $this->getJson('/api/undefined-permission')->assertForbidden()->assertJsonMissing(['secret' => 'protected']);
    }

    public function test_care_routes_all_declare_a_policy(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/') || in_array($route->uri(), ['api/ping', 'api/login', 'api/register', 'api/me', 'api/logout'], true)) {
                continue;
            }

            $rules = array_filter($route->gatherMiddleware(), fn ($rule) => str_starts_with($rule, 'can:'));
            $this->assertNotEmpty($rules, $route->uri());
        }
    }

    public function test_unknown_caregiver_type_does_not_fall_back_to_a_valid_type(): void
    {
        $user = $this->professional();
        $this->expectException(HttpException::class);

        app(CaregiverAccessService::class)->assignedOlderAdults($user, 'unknown');
    }

    public function test_routine_service_rejects_an_unknown_role_without_a_controller(): void
    {
        $user = User::factory()->create(['role' => 'auditor', 'is_approved' => true]);
        $this->expectException(AuthorizationException::class);

        app(RoutineService::class)->listFor($user);
    }

    public function test_routine_service_validates_an_explicit_adult_filter(): void
    {
        $user = $this->professional();
        $adult = $this->adult($this->professional());
        $this->expectException(AuthorizationException::class);

        app(RoutineService::class)->listFor($user, $adult->id);
    }

    public function test_shared_names_do_not_create_family_assignments_or_expose_legacy_incidents(): void
    {
        $family = User::factory()->create(['name' => 'Familia compartida', 'role' => 'familiar', 'is_approved' => true]);
        $adult = $this->adult($this->professional());
        $adult->update(['caregiver_family' => $family->name]);
        $this->incident($adult);
        Sanctum::actingAs($family);

        $this->getJson("/api/family/older-adults/{$adult->id}")->assertForbidden();
        $this->getJson('/api/family/older-adults')->assertOk()->assertJsonCount(0, 'older_adults');
        $this->getJson('/api/incidents')->assertOk()->assertJsonCount(0, 'incidents');

        $adult->update(['family_caregiver_id' => $family->id]);
        Incident::create([
            'title' => 'Solo nombre, sin asignacion', 'adult_name' => $adult->full_name,
            'incident_date' => now()->toDateString(), 'reported_by' => $adult->created_by,
        ]);
        $this->getJson("/api/family/older-adults/{$adult->id}")->assertOk();
        $this->getJson('/api/incidents')->assertOk()->assertJsonCount(1, 'incidents')
            ->assertJsonMissing(['title' => 'Solo nombre, sin asignacion']);
        $this->getJson('/api/family/incidents')->assertOk()->assertJsonCount(1, 'incidents');
    }

    public function test_reassignment_removes_access_to_notes_routines_incidents_and_medication(): void
    {
        $owner = $this->professional();
        $adult = $this->adult($owner);
        $note = RoutineNote::create([
            'older_adult_id' => $adult->id, 'professional_caregiver_id' => $owner->id,
            'content' => 'Nota original', 'note_date' => now()->toDateString(),
        ]);
        $routine = Rutina::create([
            'older_adult_id' => $adult->id, 'created_by' => $owner->id,
            'nombre' => 'Rutina original', 'horario' => '09:00', 'actividades' => ['Caminar'],
        ]);
        $incident = $this->incident($adult);
        $medication = Medication::create(['name' => 'Medicamento', 'is_active' => true]);
        $assignment = OlderAdultMedication::create([
            'older_adult_id' => $adult->id, 'medication_id' => $medication->id,
            'dosage' => '1 tableta', 'schedule' => '09:00', 'is_active' => true,
        ]);
        Sanctum::actingAs($owner);
        $this->getJson("/api/professional/routine-notes/{$note->id}")->assertOk();

        $adult->update(['professional_caregiver_id' => $this->professional()->id]);
        $this->getJson("/api/professional/older-adults/{$adult->id}")->assertForbidden();
        $this->getJson("/api/professional/routine-notes/{$note->id}")->assertForbidden();
        $this->putJson("/api/professional/routine-notes/{$note->id}", ['content' => 'Cambio indebido'])->assertForbidden();
        $this->deleteJson("/api/professional/routine-notes/{$note->id}")->assertForbidden();
        $this->deleteJson("/api/rutinas/{$routine->id}")->assertForbidden();
        $this->postJson('/api/rutinas', [
            'older_adult_id' => $adult->id, 'nombre' => 'Indebida', 'horario' => '10:00', 'actividades' => ['Caminar'],
        ])->assertForbidden();
        $this->patchJson("/api/professional/incidents/{$incident->id}", ['description' => 'Cambio indebido'])->assertForbidden();
        $this->postJson("/api/medications/{$assignment->id}/taken", [])->assertForbidden();
        $this->getJson('/api/incidents')->assertOk()->assertJsonCount(0, 'incidents');
        $this->assertDatabaseHas('routine_notes', ['id' => $note->id, 'content' => 'Nota original']);
        $this->assertDatabaseHas('rutinas', ['id' => $routine->id]);
        $this->assertDatabaseHas('incidents', ['id' => $incident->id, 'description' => 'Incidente original']);
        $this->assertDatabaseCount('medication_administrations', 0);
    }

    public function test_schedule_and_vacation_policies_require_ownership_or_an_explicit_admin_operation(): void
    {
        $owner = $this->professional();
        $other = $this->professional();
        $admin = User::factory()->create(['role' => 'admin']);
        $schedule = CaregiverSchedule::create(['user_id' => $owner->id, 'day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '16:00']);
        $vacation = VacationRequest::create([
            'user_id' => $owner->id, 'start_date' => '2026-11-01', 'end_date' => '2026-11-02', 'reason' => 'Descanso', 'status' => 'pending',
        ]);

        $this->assertTrue(Gate::forUser($owner)->allows('update', $schedule));
        $this->assertFalse(Gate::forUser($other)->allows('update', $schedule));
        $this->assertFalse(Gate::forUser($other)->allows('requestChange', $schedule));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $schedule));
        $this->assertFalse(Gate::forUser($admin)->allows('requestChange', $schedule));
        $this->assertTrue(Gate::forUser($owner)->allows('view', $vacation));
        $this->assertFalse(Gate::forUser($other)->allows('view', $vacation));
        $this->assertFalse(Gate::forUser($owner)->allows('review', $vacation));
        $this->assertTrue(Gate::forUser($admin)->allows('review', $vacation));
    }

    private function professional(): User
    {
        return User::factory()->create(['role' => 'profesional', 'is_approved' => true]);
    }

    private function adult(User $professional): OlderAdult
    {
        return OlderAdult::create([
            'full_name' => 'Adulto protegido', 'status' => 'Estable',
            'professional_caregiver_id' => $professional->id, 'created_by' => $professional->id,
        ]);
    }

    private function incident(OlderAdult $adult): Incident
    {
        return Incident::create([
            'title' => 'Incidente protegido', 'description' => 'Incidente original',
            'older_adult_id' => $adult->id, 'adult_name' => $adult->full_name,
            'incident_date' => now()->toDateString(), 'reported_by' => $adult->created_by,
        ]);
    }
}
