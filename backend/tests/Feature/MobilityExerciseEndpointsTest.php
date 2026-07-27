<?php

namespace Tests\Feature;

use App\Models\MobilityExercise;
use App\Models\User;
use Database\Seeders\MobilityExerciseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobilityExerciseEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_mobility_exercises(): void
    {
        $this->getJson('/api/mobility-exercises')->assertUnauthorized();
    }

    public function test_approved_professional_can_list_only_active_exercises(): void
    {
        MobilityExercise::create($this->exerciseData(['title' => 'Ejercicio activo', 'slug' => 'activo']));
        MobilityExercise::create($this->exerciseData([
            'title' => 'Ejercicio inactivo',
            'slug' => 'inactivo',
            'is_active' => false,
        ]));

        Sanctum::actingAs(User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]));

        $this->getJson('/api/mobility-exercises')
            ->assertOk()
            ->assertJsonCount(1, 'exercises')
            ->assertJsonPath('exercises.0.title', 'Ejercicio activo')
            ->assertJsonPath('exercises.0.duration', '3 minutos');
    }

    public function test_unapproved_professional_and_family_user_are_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'profesional',
            'is_approved' => false,
        ]));
        $this->getJson('/api/mobility-exercises')->assertForbidden();

        Sanctum::actingAs(User::factory()->create([
            'role' => 'familiar',
            'is_approved' => true,
        ]));
        $this->getJson('/api/mobility-exercises')->assertForbidden();
    }

    public function test_admin_can_create_show_update_and_delete_an_exercise(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        Sanctum::actingAs($admin);

        $created = $this->postJson('/api/admin/mobility-exercises', $this->exerciseData([
            'title' => 'Rotación de muñecas',
            'slug' => null,
        ]))
            ->assertCreated()
            ->assertJsonPath('message', 'Ejercicio de movilidad creado correctamente.')
            ->assertJsonPath('exercise.slug', 'rotacion-de-munecas')
            ->assertJsonPath('exercise.created_at', fn ($value) => is_string($value))
            ->json('exercise');

        $exerciseId = $created['id'];

        $this->getJson("/api/admin/mobility-exercises/{$exerciseId}")
            ->assertOk()
            ->assertJsonPath('exercise.title', 'Rotación de muñecas');

        $this->putJson("/api/admin/mobility-exercises/{$exerciseId}", $this->exerciseData([
            'title' => 'Rotación suave de muñecas',
            'slug' => 'rotacion-suave-munecas',
            'duration_minutes' => 4,
            'is_active' => false,
        ]))
            ->assertOk()
            ->assertJsonPath('exercise.title', 'Rotación suave de muñecas')
            ->assertJsonPath('exercise.duration_minutes', 4)
            ->assertJsonPath('exercise.is_active', false);

        $this->assertDatabaseHas('mobility_exercises', [
            'id' => $exerciseId,
            'slug' => 'rotacion-suave-munecas',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $this->deleteJson("/api/admin/mobility-exercises/{$exerciseId}")
            ->assertOk()
            ->assertJsonPath('message', 'Ejercicio de movilidad eliminado correctamente.');

        $this->assertDatabaseMissing('mobility_exercises', ['id' => $exerciseId]);
    }

    public function test_non_admin_cannot_mutate_exercises(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]));

        $this->postJson('/api/admin/mobility-exercises', $this->exerciseData())
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo un administrador puede realizar esta accion.');
    }

    public function test_admin_payload_is_validated(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_approved' => true]));

        $this->postJson('/api/admin/mobility-exercises', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title',
                'focus',
                'duration_minutes',
                'repetitions',
                'instructions',
                'precaution',
            ]);
    }

    public function test_inactive_exercise_is_hidden_from_professional_but_visible_to_admin(): void
    {
        $exercise = MobilityExercise::create($this->exerciseData(['is_active' => false]));

        Sanctum::actingAs(User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]));
        $this->getJson("/api/mobility-exercises/{$exercise->id}")->assertNotFound();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_approved' => true]));
        $this->getJson("/api/admin/mobility-exercises/{$exercise->id}")
            ->assertOk()
            ->assertJsonPath('exercise.is_active', false);
    }

    public function test_seeder_creates_the_four_default_exercises_idempotently(): void
    {
        $this->seed(MobilityExerciseSeeder::class);
        $this->seed(MobilityExerciseSeeder::class);

        $this->assertDatabaseCount('mobility_exercises', 4);
        $this->assertDatabaseHas('mobility_exercises', ['slug' => 'cuello-suave']);
        $this->assertDatabaseHas('mobility_exercises', ['slug' => 'marcha-sentada']);
    }

    private function exerciseData(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'movilidad-prueba',
            'title' => 'Movilidad de prueba',
            'focus' => 'Brazos y hombros',
            'duration_minutes' => 3,
            'repetitions' => '5 repeticiones',
            'instructions' => ['Siéntate con la espalda recta.', 'Realiza el movimiento lentamente.'],
            'precaution' => 'Suspende el ejercicio si aparece dolor.',
            'is_active' => true,
            'sort_order' => 10,
        ], $overrides);
    }
}
