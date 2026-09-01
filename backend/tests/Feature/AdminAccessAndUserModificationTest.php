<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccessAndUserModificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_log_in_and_access_admin_routes(): void
    {
        $admin = User::factory()->create([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'is_approved' => true,
        ]);

        $loginResponse = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'admin123',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('user.id', $admin->id)
            ->assertJsonPath('user.role', 'admin');

        $token = $loginResponse->json('token');
        $this->assertNotEmpty($token);

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.id', $admin->id)
            ->assertJsonPath('user.role', 'admin');

        $this->withToken($token)->getJson('/api/admin/dashboard-summary')->assertOk();

        $this->withToken($token)->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonStructure(['users' => [['id', 'name', 'email', 'role', 'is_approved']]]);
    }

    public function test_admin_can_update_user_data(): void
    {
        $adminPassword = 'secret123';
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
            'password' => Hash::make($adminPassword),
        ]);

        $userToUpdate = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'role' => 'familiar',
            'is_approved' => false,
            'location' => 'Original Location',
            'phone' => '1234567890',
            'birthdate' => '1980-01-15',
        ]);

        $token = $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => $adminPassword,
        ])
            ->assertOk()
            ->json('token');

        $updateResponse = $this->withToken($token)->putJson(
            "/api/admin/users/{$userToUpdate->id}",
            [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
                'role' => 'familiar',
                'is_approved' => true,
                'location' => 'Updated Location',
                'phone' => '9876543210',
                'birthdate' => '1980-01-15',
            ]
        );

        $updateResponse
            ->assertOk()
            ->assertJsonPath('user.name', 'Updated Name')
            ->assertJsonPath('user.email', 'updated@example.com')
            ->assertJsonPath('user.location', 'Updated Location')
            ->assertJsonPath('user.phone', '9876543210')
            ->assertJsonPath('user.is_approved', true);

        $this->assertDatabaseHas('users', [
            'id' => $userToUpdate->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'location' => 'Updated Location',
            'phone' => '9876543210',
            'is_approved' => true,
        ]);
    }

    public function test_admin_can_retrieve_updated_user_details(): void
    {
        $adminPassword = 'secret123';
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
            'password' => Hash::make($adminPassword),
        ]);

        $userToUpdate = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'location' => 'Original Location',
            'phone' => '1111111111',
        ]);

        $token = $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => $adminPassword,
        ])
            ->assertOk()
            ->json('token');

        $this->withToken($token)->putJson(
            "/api/admin/users/{$userToUpdate->id}",
            [
                'name' => 'Modified Name',
                'email' => 'modified@example.com',
                'role' => 'familiar',
                'is_approved' => false,
                'location' => 'Modified Location',
                'phone' => '2222222222',
                'birthdate' => null,
            ]
        )
            ->assertOk();

        $this->withToken($token)->getJson("/api/admin/users/{$userToUpdate->id}")
            ->assertOk()
            ->assertJsonPath('user.name', 'Modified Name')
            ->assertJsonPath('user.email', 'modified@example.com')
            ->assertJsonPath('user.location', 'Modified Location')
            ->assertJsonPath('user.phone', '2222222222')
            ->assertJsonPath('user.is_approved', false);
    }
}
