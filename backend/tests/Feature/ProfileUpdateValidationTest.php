<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileUpdateValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_rejects_invalid_phone_email_and_birthdate_without_changing_the_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Ana Cuidadora',
            'email' => 'ana@example.com',
            'phone' => '55551234',
            'birthdate' => '1980-05-10',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/me', [
            'name' => 'Nombre que no debe persistir',
            'email' => 'correo-invalido',
            'phone' => ['telefono-invalido'],
            'birthdate' => 'fecha-invalida',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone', 'email', 'birthdate']);

        $user->refresh();

        $this->assertSame('Ana Cuidadora', $user->name);
        $this->assertSame('ana@example.com', $user->email);
        $this->assertSame('55551234', $user->phone);
        $this->assertSame('1980-05-10', $user->birthdate?->toDateString());
    }
}
