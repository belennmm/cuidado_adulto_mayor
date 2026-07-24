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

    public function test_admin_can_list_medication_inventory(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]));

        $losartan = Medication::create([
            'name' => 'Losartan',
            'presentation' => 'Tableta 50mg',
            'quantity' => 24,
            'unit' => 'tabletas',
            'minimum_stock' => 5,
            'expiration_date' => '2030-12-31',
            'is_active' => true,
        ]);

        $acetaminophen = Medication::create([
            'name' => 'Acetaminofen',
            'presentation' => 'Tableta 500mg',
            'quantity' => 8,
            'unit' => 'cajas',
            'minimum_stock' => 10,
            'expiration_date' => '2030-06-30',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/admin/medications/inventory');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'inventory')
            ->assertJsonStructure([
                'inventory' => [
                    '*' => [
                        'id',
                        'name',
                        'presentation',
                        'quantity',
                        'unit',
                        'minimum_stock',
                        'expiration_date',
                        'is_active',
                        'status',
                        'status_label',
                        'assigned_patients',
                        'active_assignments',
                        'administrations_count',
                    ],
                ],
            ])
            ->assertJsonPath('inventory.0.id', $acetaminophen->id)
            ->assertJsonPath('inventory.0.name', 'Acetaminofen')
            ->assertJsonPath('inventory.0.presentation', 'Tableta 500mg')
            ->assertJsonPath('inventory.0.quantity', 8)
            ->assertJsonPath('inventory.0.unit', 'cajas')
            ->assertJsonPath('inventory.0.minimum_stock', 10)
            ->assertJsonPath('inventory.0.expiration_date', '2030-06-30')
            ->assertJsonPath('inventory.0.is_active', false)
            ->assertJsonPath('inventory.0.status', 'low_stock')
            ->assertJsonPath('inventory.0.status_label', 'Bajo stock')
            ->assertJsonPath('inventory.0.assigned_patients', 0)
            ->assertJsonPath('inventory.0.active_assignments', 0)
            ->assertJsonPath('inventory.0.administrations_count', 0)
            ->assertJsonPath('inventory.1.id', $losartan->id)
            ->assertJsonPath('inventory.1.name', 'Losartan')
            ->assertJsonPath('inventory.1.presentation', 'Tableta 50mg')
            ->assertJsonPath('inventory.1.quantity', 24)
            ->assertJsonPath('inventory.1.unit', 'tabletas')
            ->assertJsonPath('inventory.1.minimum_stock', 5)
            ->assertJsonPath('inventory.1.expiration_date', '2030-12-31')
            ->assertJsonPath('inventory.1.is_active', true)
            ->assertJsonPath('inventory.1.status', 'available')
            ->assertJsonPath('inventory.1.status_label', 'Disponible')
            ->assertJsonPath('inventory.1.assigned_patients', 0)
            ->assertJsonPath('inventory.1.active_assignments', 0)
            ->assertJsonPath('inventory.1.administrations_count', 0);
    }

    public function test_admin_receives_empty_medication_inventory(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]));

        $this->assertDatabaseCount('medications', 0);

        $this->getJson('/api/admin/medications/inventory')
            ->assertOk()
            ->assertJsonCount(0, 'inventory')
            ->assertExactJson([
                'inventory' => [],
            ]);
    }

    public function test_guest_cannot_list_medication_inventory(): void
    {
        $this->getJson('/api/admin/medications/inventory')
            ->assertUnauthorized();
    }

    public function test_non_admin_cannot_list_medication_inventory(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'familiar',
            'is_approved' => true,
        ]));

        $this->getJson('/api/admin/medications/inventory')
            ->assertForbidden()
            ->assertJsonPath('message', 'Solo un administrador puede realizar esta accion.');
    }

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

    public function test_admin_can_delete_existing_medication(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'is_approved' => true,
        ]));

        $medication = Medication::create([
            'name' => 'Loratadina',
            'presentation' => 'Jarabe',
            'quantity' => 4,
            'unit' => 'frascos',
            'minimum_stock' => 2,
            'expiration_date' => '2027-08-01',
            'is_active' => true,
        ]);

        $this->deleteJson("/api/admin/medications/inventory/{$medication->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Medicamento eliminado correctamente.');

        $this->assertDatabaseMissing('medications', [
            'id' => $medication->id,
        ]);
    }
}
