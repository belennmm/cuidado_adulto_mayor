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

    public function test_admin_can_increase_and_decrease_medication_stock(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]));

        $medication = Medication::create([
            'name' => 'Acetaminofen',
            'presentation' => 'Tableta 500mg',
            'quantity' => 20,
            'unit' => 'tabletas',
            'minimum_stock' => 5,
            'expiration_date' => '2027-03-10',
            'is_active' => true,
        ]);

        $this->patchJson("/api/admin/medications/inventory/{$medication->id}/stock", [
            'action' => 'increase',
            'amount' => 15,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Stock aumentado correctamente.')
            ->assertJsonPath('medication.id', $medication->id)
            ->assertJsonPath('medication.quantity', 35);

        $this->patchJson("/api/admin/medications/inventory/{$medication->id}/stock", [
            'action' => 'decrease',
            'amount' => 8,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Stock reducido correctamente.')
            ->assertJsonPath('medication.id', $medication->id)
            ->assertJsonPath('medication.quantity', 27);

        $this->assertDatabaseHas('medications', [
            'id' => $medication->id,
            'quantity' => 27,
        ]);
    }

    public function test_admin_cannot_reduce_medication_stock_below_zero(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]));

        $medication = Medication::create([
            'name' => 'Ibuprofeno',
            'presentation' => 'Capsula 400mg',
            'quantity' => 6,
            'unit' => 'capsulas',
            'minimum_stock' => 3,
            'expiration_date' => '2027-06-20',
            'is_active' => true,
        ]);

        $this->patchJson("/api/admin/medications/inventory/{$medication->id}/stock", [
            'action' => 'decrease',
            'amount' => 10,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'La cantidad no puede quedar negativa.');

        $this->assertDatabaseHas('medications', [
            'id' => $medication->id,
            'quantity' => 6,
        ]);
    }
}
