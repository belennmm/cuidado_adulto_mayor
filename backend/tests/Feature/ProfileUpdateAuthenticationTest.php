<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_update_a_profile(): void
    {
        User::factory()->create([
            'name' => 'Ana Cuidadora',
            'email' => 'ana@example.com',
            'phone' => '55551234',
            'location' => 'Zona 1',
            'birthdate' => '1980-05-10',
            'is_approved' => true,
        ]);

        $response = $this->putJson('/api/me', [
            'name' => 'Nombre no autorizado',
            'email' => 'intruso@example.com',
            'phone' => '55559876',
            'location' => 'Zona 10',
            'birthdate' => '1990-01-01',
        ]);

        // Paso 2: verificar que la respuesta sea HTTP 401.
    }
}
