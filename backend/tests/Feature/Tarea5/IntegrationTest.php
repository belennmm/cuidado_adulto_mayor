<?php

namespace Tests\Feature\Tarea5;

use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_i01_login_persists_token_and_authenticates_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'integracion@example.com', 'password' => Hash::make('secret123'),
            'role' => 'profesional', 'is_approved' => true,
        ]);
        $token = $this->postJson('/api/login', [
            'email' => $user->email, 'password' => 'secret123',
        ])->assertOk()->json('token');

        $this->assertIsString($token);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->withToken($token)->getJson('/api/me')
            ->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_i02_create_adult_persists_medication_and_acquisition(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $family = User::factory()->create(['role' => 'familiar', 'is_approved' => true]);
        $professional = User::factory()->create(['role' => 'profesional', 'is_approved' => true]);
        Sanctum::actingAs($admin);
        $id = $this->postJson('/api/admin/older-adults', [
            'full_name' => 'Rosa Integracion', 'age' => 82, 'birthdate' => '1944-05-12',
            'gender' => 'Femenino', 'room' => 'T5-101', 'status' => 'Estable',
            'family_caregiver_id' => $family->id, 'professional_caregiver_id' => $professional->id,
            'medications' => [[
                'name' => 'Losartan T5', 'dosage' => '1 tableta', 'schedule' => '08:00',
                'days' => ['lunes'], 'quantity' => 7, 'unit' => 'tabletas', 'minimum_stock' => 2,
            ]],
        ])->assertCreated()->assertJsonPath('older_adult.medications.0.name', 'Losartan T5')
            ->json('older_adult.id');

        $this->assertDatabaseHas('older_adults', ['id' => $id, 'created_by' => $admin->id]);
        $this->assertDatabaseHas('medications', ['name' => 'Losartan T5']);
        $this->assertDatabaseHas('older_adult_medications', ['older_adult_id' => $id, 'quantity' => 7]);
        $this->assertDatabaseHas('medication_acquisitions', ['older_adult_id' => $id, 'quantity' => 7]);
        $this->getJson('/api/admin/older-adults/'.$id)->assertOk()
            ->assertJsonPath('older_adult.full_name', 'Rosa Integracion');
    }

    public function test_i03_routine_note_links_professional_and_adult_in_database(): void
    {
        $professional = User::factory()->create(['role' => 'profesional', 'is_approved' => true]);
        $adult = OlderAdult::create([
            'full_name' => 'Rosa Nota', 'room' => 'T5-102', 'status' => 'Estable',
            'professional_caregiver_id' => $professional->id, 'created_by' => $professional->id,
        ]);
        Sanctum::actingAs($professional);
        $this->postJson('/api/professional/routine-notes', [
            'older_adult_id' => $adult->id, 'content' => 'Seguimiento de integracion T5.',
        ])->assertCreated()->assertJsonPath('note.professional_caregiver.id', $professional->id)
            ->assertJsonPath('note.older_adult_id', $adult->id);
        $this->assertDatabaseHas('routine_notes', [
            'older_adult_id' => $adult->id, 'professional_caregiver_id' => $professional->id,
            'content' => 'Seguimiento de integracion T5.',
        ]);
    }
}
