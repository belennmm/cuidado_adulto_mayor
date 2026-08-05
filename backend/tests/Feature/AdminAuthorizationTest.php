<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_family_and_professional_users_cannot_access_or_modify_administrator_resources(): void
    {
        $admin = User::factory()->create([
            'name' => 'Administrador Original',
            'role' => 'admin',
            'is_approved' => true,
        ]);
        $family = User::factory()->create([
            'role' => 'familiar',
            'is_approved' => true,
        ]);
        $professional = User::factory()->create([
            'role' => 'profesional',
            'is_approved' => true,
        ]);

        $adminUpdatePayload = [
            'name' => 'Nombre no autorizado',
            'email' => 'admin.modificado@example.com',
            'role' => 'admin',
            'is_approved' => true,
        ];

        Sanctum::actingAs($family);

        $this->getJson('/api/admin/users')->assertForbidden();
        $this->putJson("/api/admin/users/{$admin->id}", $adminUpdatePayload)->assertForbidden();

        Sanctum::actingAs($professional);

        $this->getJson('/api/admin/older-adults')->assertForbidden();
        $this->postJson('/api/admin/older-adults', [
            'full_name' => 'Adulto mayor no autorizado',
            'age' => 80,
            'status' => 'Estable',
        ])->assertForbidden();

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('older_adults', 0);
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => 'Administrador Original',
            'email' => $admin->email,
            'role' => 'admin',
        ]);
        $this->assertDatabaseMissing('users', ['email' => $adminUpdatePayload['email']]);
    }
}
