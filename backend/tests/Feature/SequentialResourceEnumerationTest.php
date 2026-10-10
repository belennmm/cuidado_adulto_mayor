<?php

namespace Tests\Feature;

use App\Models\Medication;
use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SequentialResourceEnumerationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('caregiverRoles')]
    public function test_enumerating_neighboring_ids_exposes_only_assigned_adults(string $role, string $module, string $assignment): void
    {
        $user = User::factory()->create(['role' => $role, 'is_approved' => true]);
        $other = User::factory()->create(['role' => $role, 'is_approved' => true]);
        $adults = [];
        foreach ([$other, $user, $other] as $owner) {
            $adults[] = OlderAdult::create(['full_name' => 'Privado '.$owner->id, 'created_by' => $owner->id, $assignment => $owner->id]);
        }
        $headers = ['Authorization' => 'Bearer '.$user->createToken('enumeration')->plainTextToken];
        $this->assertSame($adults[0]->id + 1, $adults[1]->id);
        $this->assertSame($adults[1]->id + 1, $adults[2]->id);
        $this->getJson("/api/{$module}/older-adults", $headers)
            ->assertOk()->assertJsonCount(1, 'older_adults')->assertJsonPath('older_adults.0.id', $adults[1]->id);

        foreach ([$adults[0]->id, $adults[2]->id, $adults[2]->id + 1, $adults[2]->id + 2] as $id) {
            foreach (["/api/{$module}/older-adults/{$id}", "/api/{$module}/routines?older_adult_id={$id}", "/api/rutinas?older_adult_id={$id}"] as $path) {
                $this->getJson($path, $headers)->assertNotFound()->assertExactJson(['message' => 'Recurso no encontrado.']);
            }
            $this->postJson('/api/rutinas', [
                'older_adult_id' => $id, 'nombre' => 'Inyectada', 'horario' => '08:00', 'actividades' => ['Caminar'],
            ], $headers)->assertNotFound()->assertExactJson(['message' => 'Recurso no encontrado.']);
        }
        $this->getJson("/api/{$module}/older-adults/{$adults[1]->id}", $headers)->assertOk();
        $this->assertDatabaseCount('rutinas', 0);
    }

    public static function caregiverRoles(): array
    {
        return [
            ['familiar', 'family', 'family_caregiver_id'], ['cuidador_familiar', 'family', 'family_caregiver_id'],
            ['profesional', 'professional', 'professional_caregiver_id'], ['cuidador_profesional', 'professional', 'professional_caregiver_id'],
        ];
    }

    public function test_nested_foreign_and_missing_assignment_ids_have_the_same_response_and_no_side_effects(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $own = OlderAdult::create(['full_name' => 'Original', 'created_by' => $admin->id]);
        $foreign = OlderAdult::create(['full_name' => 'Ajeno', 'created_by' => $admin->id]);
        $medication = Medication::create(['name' => 'Original', 'is_active' => true]);
        $assignment = OlderAdultMedication::create(['older_adult_id' => $foreign->id, 'medication_id' => $medication->id, 'is_active' => true]);
        $headers = ['Authorization' => 'Bearer '.$admin->createToken('nested-enumeration')->plainTextToken];
        foreach ([$assignment->id, $assignment->id + 1, $assignment->id + 2] as $id) {
            $this->putJson('/api/admin/older-adults/'.$own->id, [
                'full_name' => 'No debe persistir', 'medications' => [['id' => $id, 'name' => 'No debe crearse']],
            ], $headers)->assertNotFound()->assertExactJson(['message' => 'Recurso no encontrado.']);
        }
        $this->assertDatabaseHas('older_adults', ['id' => $own->id, 'full_name' => 'Original']);
        $this->assertDatabaseCount('medications', 1);
        $this->assertDatabaseCount('older_adult_medications', 1);
    }
}
