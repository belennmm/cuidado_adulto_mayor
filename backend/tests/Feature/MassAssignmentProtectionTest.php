<?php

namespace Tests\Feature;

use App\Models\Medication;
use App\Models\MobilityExercise;
use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
use App\Models\User;
use App\Services\AdminUserService;
use App\Services\OlderAdultService;
use Database\Seeders\InitialDataSeeder;
use Database\Seeders\SecurityScanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MassAssignmentProtectionTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('roles')]
    public function test_model_mass_assignment_cannot_change_security_attributes(string $role): void
    {
        $user = User::factory()->create(['role' => $role, 'is_approved' => true]);
        $originalHash = $user->password;
        $token = $user->createToken('preserved');
        $user->update([
            'name' => 'Permitido', 'role' => 'admin', 'is_approved' => false,
            'password' => 'Injected-Password!123', 'privacy_consent_at' => '2000-01-01', 'privacy_policy_version' => 'inyectada',
            'id' => 999999, 'created_at' => '2000-01-01', 'email_verified_at' => '2000-01-01', 'remember_token' => 'inyectado',
        ]);
        $user->refresh();
        $this->assertSame('Permitido', $user->name);
        $this->assertSame($role, $user->role);
        $this->assertTrue($user->is_approved);
        $this->assertSame($originalHash, $user->password);
        $this->assertNull($user->privacy_consent_at);
        $this->assertNull($user->privacy_policy_version);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Permitido']);
        $this->assertDatabaseMissing('users', ['id' => 999999]);
    }

    public static function roles(): array
    {
        return [['admin'], ['profesional'], ['familiar']];
    }

    public function test_mass_assignment_on_a_new_user_cannot_set_an_administrative_role(): void
    {
        $user = new User([
            'name' => 'Intento', 'email' => 'intento@example.test', 'password' => 'Injected-Password!123',
            'role' => 'admin', 'is_approved' => true, 'privacy_policy_version' => 'inyectada',
        ]);
        $this->assertSame(['name', 'email'], array_keys($user->getAttributes()));
    }

    public function test_controlled_admin_operations_still_write_security_fields_and_revoke_tokens(): void
    {
        $service = app(AdminUserService::class);
        $user = $service->create([
            'name' => 'Autorizado', 'email' => 'authorized@example.test', 'password' => 'Authorized-Password!123',
            'role' => 'profesional', 'id' => 999999, 'privacy_policy_version' => 'inyectada',
        ]);
        $this->assertTrue($user->is_approved);
        $this->assertTrue(Hash::check('Authorized-Password!123', $user->password));
        $this->assertNull($user->privacy_policy_version);
        $user->createToken('revoked');
        $service->update($user, ['role' => 'familiar', 'is_approved' => false]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertSame('familiar', $user->refresh()->role);
        $this->assertFalse($user->is_approved);
        $service->approve($user);
        $this->assertTrue($user->refresh()->is_approved);
    }

    public function test_nested_medication_payload_cannot_rewrite_ownership_or_stock_metadata(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $adult = OlderAdult::create(['full_name' => 'Original', 'created_by' => $admin->id]);
        $other = OlderAdult::create(['full_name' => 'Ajeno', 'created_by' => $admin->id]);
        $medication = Medication::create(['name' => 'Medicamento', 'is_active' => true]);
        $assignment = OlderAdultMedication::create([
            'older_adult_id' => $adult->id, 'medication_id' => $medication->id, 'quantity' => 5,
            'unit' => 'tabletas', 'is_active' => true,
        ]);
        app(OlderAdultService::class)->update($adult, [
            'full_name' => 'Permitido', 'created_by' => 999999, 'id' => 999999,
            'medications' => [[
                'id' => $assignment->id, 'name' => 'Medicamento', 'dosage' => '1 tableta',
                'older_adult_id' => $other->id, 'medication_id' => 999999, 'quantity' => 99999,
                'is_active' => false, 'created_at' => '2000-01-01', 'administrations_count' => 999,
            ]],
        ]);
        $this->assertDatabaseHas('older_adults', ['id' => $adult->id, 'created_by' => $admin->id, 'full_name' => 'Permitido']);
        $this->assertDatabaseHas('older_adult_medications', [
            'id' => $assignment->id, 'older_adult_id' => $adult->id, 'medication_id' => $medication->id,
            'quantity' => 5, 'is_active' => true, 'dosage' => '1 tableta',
        ]);
        $this->assertDatabaseCount('medication_acquisitions', 0);
    }

    public function test_exercise_http_payload_preserves_server_owned_audit_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $other = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $data = [
            'title' => 'Ejercicio', 'focus' => 'Movilidad', 'duration_minutes' => 5, 'repetitions' => '5',
            'instructions' => ['Mover lentamente'], 'precaution' => 'Con supervision', 'slug' => 'ejercicio',
            'created_by' => $other->id, 'updated_by' => $other->id, 'id' => 999999, 'created_at' => '2000-01-01',
        ];
        $headers = ['Authorization' => 'Bearer '.$admin->createToken('audit')->plainTextToken];
        $id = $this->postJson('/api/admin/mobility-exercises', $data, $headers)->assertCreated()->json('exercise.id');
        $this->putJson('/api/admin/mobility-exercises/'.$id, $data, $headers)->assertOk();
        $exercise = MobilityExercise::findOrFail($id);
        $this->assertSame($admin->id, $exercise->created_by);
        $this->assertSame($admin->id, $exercise->updated_by);
        $this->assertNotSame('2000-01-01', $exercise->created_at->toDateString());
    }

    public function test_explicit_test_account_seeder_remains_compatible_with_guarded_fields(): void
    {
        $seeder = new SecurityScanSeeder;
        $seeder->run();
        $seeder->run();
        $this->assertDatabaseCount('users', 3);
        $admin = User::query()->where('email', 'zap.admin@example.test')->sole();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue($admin->is_approved);
        $this->assertTrue(Hash::check('ZapAdmin-2026!', $admin->password));
    }

    public function test_demonstration_seeder_uses_explicit_security_attribute_writes(): void
    {
        (new InitialDataSeeder)->run();
        $admin = User::query()->where('email', 'belen@gmail.com')->sole();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue($admin->is_approved);
        $this->assertTrue(Hash::check('belen123', $admin->password));
        $this->assertDatabaseHas('older_adults', ['created_by' => $admin->id]);
        $this->assertDatabaseHas('users', ['role' => 'profesional', 'is_approved' => true]);
        $this->assertDatabaseHas('users', ['role' => 'familiar', 'is_approved' => false]);
    }
}
