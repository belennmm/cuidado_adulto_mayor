<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OlderAdultDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_delete_an_older_adult(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($admin);

        $olderAdult = $this->postJson('/api/admin/older-adults', [
            'full_name' => 'Adulto Prueba Eliminacion',
            'age' => 75,
            'gender' => 'Masculino',
            'room' => 'TEST-01',
            'status' => 'Estable',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Adulto mayor creado correctamente.')
            ->assertJsonPath('older_adult.full_name', 'Adulto Prueba Eliminacion')
            ->json('older_adult');

        $olderAdultId = $olderAdult['id'];

        $this->assertDatabaseHas('older_adults', [
            'id' => $olderAdultId,
            'full_name' => 'Adulto Prueba Eliminacion',
            'created_by' => $admin->id,
        ]);

        $this->deleteJson("/api/admin/older-adults/{$olderAdultId}")
            ->assertOk()
            ->assertExactJson([
                'message' => 'Adulto mayor eliminado correctamente.',
            ]);

        $this->assertDatabaseMissing('older_adults', [
            'id' => $olderAdultId,
        ]);

        $this->getJson("/api/admin/older-adults/{$olderAdultId}")
            ->assertNotFound();
    }
}
