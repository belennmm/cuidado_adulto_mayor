<?php

namespace Tests\Feature;

use App\Models\Medication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MedicationInventoryEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_existing_medication(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]));

        $medication = Medication::create([
            'name' => 'Losartan',
            'presentation' => 'Tableta',
            'quantity' => 12,
            'unit' => 'tabletas',
            'minimum_stock' => 5,
            'expiration_date' => '2026-12-31',
            'is_active' => true,
        ]);

        $this->putJson("/api/admin/medications/inventory/{$medication->id}", [
            'name' => 'Losartan potasico',
            'presentation' => 'Tableta 50mg',
            'quantity' => 30,
            'unit' => 'cajas',
            'minimum_stock' => 8,
            'expiration_date' => '2027-01-15',
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Medicamento actualizado correctamente.')
            ->assertJsonPath('medication.id', $medication->id)
            ->assertJsonPath('medication.name', 'Losartan potasico')
            ->assertJsonPath('medication.presentation', 'Tableta 50mg')
            ->assertJsonPath('medication.quantity', 30)
            ->assertJsonPath('medication.unit', 'cajas')
            ->assertJsonPath('medication.minimum_stock', 8)
            ->assertJsonPath('medication.expiration_date', '2027-01-15')
            ->assertJsonPath('medication.is_active', false);

        $this->assertDatabaseHas('medications', [
            'id' => $medication->id,
            'name' => 'Losartan potasico',
            'presentation' => 'Tableta 50mg',
            'quantity' => 30,
            'unit' => 'cajas',
            'minimum_stock' => 8,
            'expiration_date' => '2027-01-15 00:00:00',
            'is_active' => false,
        ]);
    }
}
