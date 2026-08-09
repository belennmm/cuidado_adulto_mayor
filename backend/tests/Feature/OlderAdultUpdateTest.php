<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OlderAdultUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_an_older_adults_information(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($admin);

        $olderAdult = $this->postJson('/api/admin/older-adults', [
            'full_name' => 'Adulto Prueba Actualizacion',
            'age' => 74,
            'gender' => 'Masculino',
            'room' => 'TEST-02',
            'status' => 'Estable',
        ])
            ->assertCreated()
            ->json('older_adult');

        $updatedData = [
            'full_name' => 'Adulto Actualizado',
            'age' => 75,
            'gender' => 'Masculino',
            'room' => 'TEST-03',
            'status' => 'En observacion',
        ];

        $this->putJson("/api/admin/older-adults/{$olderAdult['id']}", $updatedData)
            ->assertOk()
            ->assertJsonPath('message', 'Adulto mayor actualizado correctamente.')
            ->assertJsonPath('older_adult.id', $olderAdult['id'])
            ->assertJsonPath('older_adult.full_name', 'Adulto Actualizado')
            ->assertJsonPath('older_adult.age', 75)
            ->assertJsonPath('older_adult.room', 'TEST-03')
            ->assertJsonPath('older_adult.status', 'En observacion');

        $this->assertDatabaseHas('older_adults', [
            'id' => $olderAdult['id'],
            'full_name' => 'Adulto Actualizado',
            'age' => 75,
            'room' => 'TEST-03',
            'status' => 'En observacion',
            'created_by' => $admin->id,
        ]);

        $this->assertDatabaseMissing('older_adults', [
            'id' => $olderAdult['id'],
            'full_name' => 'Adulto Prueba Actualizacion',
            'room' => 'TEST-02',
            'status' => 'Estable',
        ]);
    }
}
