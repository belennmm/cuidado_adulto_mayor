<?php

namespace Tests\NonFunctional\Reliability;

use App\Models\Incident;
use App\Models\MedicationAcquisition;
use App\Models\MedicationAdministration;
use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
use App\Models\RoutineNote;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** SCRUM-1019: copia SQLite real para validar modelos/API con las migraciones Laravel. */
class RecoveredCareDataTest extends TestCase
{
    use DatabaseMigrations;

    public function test_recovered_copy_preserves_care_data_relationships_and_access_scope(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));
        $sourceConnection = DB::getDefaultConnection();
        $copy = tempnam(sys_get_temp_dir(), 'care-recovery-');
        $this->assertNotFalse($copy);

        try {
            $plaintext = 'Recuperacion!2026-Sintetica';
            $admin = User::factory()->create([
                'role' => 'admin', 'is_approved' => true, 'password' => Hash::make($plaintext),
            ]);
            $professional = User::factory()->approvedProfessional()->create();
            $otherProfessional = User::factory()->approvedProfessional()->create();
            $family = User::factory()->approvedFamily()->create();
            $adult = OlderAdult::factory()->create([
                'full_name' => 'Adulto sintético recuperado', 'created_by' => $admin->id,
                'professional_caregiver_id' => $professional->id, 'family_caregiver_id' => $family->id,
            ]);
            $otherAdult = OlderAdult::factory()->create(['professional_caregiver_id' => $otherProfessional->id]);
            $assignment = OlderAdultMedication::factory()->create([
                'older_adult_id' => $adult->id, 'dosage' => '1 tableta', 'quantity' => 7,
                'schedule' => '08:00', 'days' => ['lunes', 'miércoles'],
            ]);
            $routine = $adult->rutinas()->create([
                'created_by' => $professional->id, 'nombre' => 'Rutina recuperada',
                'horario' => '08:00', 'actividades' => ['Movilidad', 'Hidratación'],
            ]);
            $otherAdult->rutinas()->create([
                'created_by' => $otherProfessional->id, 'nombre' => 'Rutina de otro cuidador',
                'horario' => '09:00', 'actividades' => ['Actividad sintética'],
            ]);
            $incident = Incident::create([
                'older_adult_id' => $adult->id, 'adult_name' => $adult->full_name,
                'reported_by' => $professional->id, 'title' => 'Incidente recuperado',
                'description' => 'Descripción sintética conservada', 'severity' => 'media',
                'status' => 'abierto', 'incident_date' => '2026-10-20', 'incident_time' => '09:30:00',
            ]);
            Incident::create([
                'older_adult_id' => $otherAdult->id, 'adult_name' => $otherAdult->full_name,
                'reported_by' => $otherProfessional->id, 'title' => 'Incidente de otro cuidador',
                'severity' => 'baja', 'status' => 'abierto', 'incident_date' => '2026-10-20',
            ]);
            $note = RoutineNote::create([
                'older_adult_id' => $adult->id, 'professional_caregiver_id' => $professional->id,
                'content' => 'Seguimiento sintético recuperado', 'note_date' => '2026-10-20',
            ]);
            $administration = MedicationAdministration::create([
                'older_adult_id' => $adult->id, 'older_adult_medication_id' => $assignment->id,
                'medication_id' => $assignment->medication_id, 'recorded_by' => $professional->id,
                'dosage' => '1 tableta', 'administration_date' => '2026-10-20', 'administration_time' => '08:00:00',
            ]);
            $acquisition = MedicationAcquisition::create([
                'older_adult_id' => $adult->id, 'medication_id' => $assignment->medication_id,
                'quantity' => 7, 'acquired_at' => '2026-10-20 07:00:00',
            ]);

            $tables = ['users', 'older_adults', 'medications', 'older_adult_medications', 'rutinas',
                'incidents', 'routine_notes', 'medication_administrations', 'medication_acquisitions'];
            $reference = [];
            foreach ($tables as $table) {
                $reference[$table] = DB::table($table)->orderBy('id')->get()->toArray();
            }
            // VACUUM INTO copia esquema y datos de SQLite, sin una transacción de testing abierta.
            $pdo = DB::connection()->getPdo();
            $pdo->exec('VACUUM INTO '.$pdo->quote($copy));
            DB::table('older_adults')->where('id', $adult->id)->update(['full_name' => 'Fuente modificada después del respaldo']);

            config(['database.connections.recovered_care_test' => array_replace(
                config('database.connections.sqlite'), ['database' => $copy],
            )]);
            DB::setDefaultConnection('recovered_care_test');
            $this->assertSame($copy, DB::connection()->getDatabaseName());
            foreach ($tables as $table) {
                $this->assertEquals($reference[$table], DB::table($table)->orderBy('id')->get()->toArray(), $table);
            }
            $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
            $recoveredAdmin = User::findOrFail($admin->id);
            $this->assertTrue(Hash::check($plaintext, $recoveredAdmin->password));
            $this->assertSame('admin', $recoveredAdmin->role);
            $this->assertTrue($recoveredAdmin->is_approved);
            $recovered = OlderAdult::findOrFail($adult->id);
            $this->assertSame('Adulto sintético recuperado', $recovered->full_name);
            $this->assertSame($admin->id, $recovered->creator->id);
            $this->assertSame($professional->id, $recovered->professionalCaregiver->id);
            $this->assertSame($family->id, $recovered->familyCaregiver->id);
            $recoveredAssignment = $recovered->medicationAssignments->sole();
            $this->assertSame($assignment->medication_id, $recoveredAssignment->medication->id);
            $this->assertSame('1 tableta', $recoveredAssignment->dosage);
            $this->assertSame(7, $recoveredAssignment->quantity);
            $this->assertSame(['lunes', 'miércoles'], $recoveredAssignment->days);
            $this->assertSame($routine->id, $recovered->rutinas->sole()->id);
            $this->assertSame(['Movilidad', 'Hidratación'], $recovered->rutinas->sole()->actividades);
            $this->assertSame($professional->id, $recovered->rutinas->sole()->creator->id);
            $this->assertSame($incident->id, $recovered->incidents->sole()->id);
            $this->assertSame($professional->id, $recovered->incidents->sole()->reporter->id);
            $this->assertSame($note->id, $recovered->routineNotes->sole()->id);
            $this->assertSame($professional->id, $recovered->routineNotes->sole()->professionalCaregiver->id);
            $recoveredAdministration = MedicationAdministration::findOrFail($administration->id);
            $this->assertSame($adult->id, $recoveredAdministration->olderAdult->id);
            $this->assertSame($assignment->id, $recoveredAdministration->olderAdultMedication->id);
            $this->assertSame($assignment->medication_id, $recoveredAdministration->medication->id);
            $this->assertSame($professional->id, $recoveredAdministration->recorder->id);
            $recoveredAcquisition = MedicationAcquisition::findOrFail($acquisition->id);
            $this->assertSame($adult->id, $recoveredAcquisition->olderAdult->id);
            $this->assertSame($assignment->medication_id, $recoveredAcquisition->medication->id);

            Sanctum::actingAs($recoveredAdmin);
            $this->getJson('/api/admin/older-adults/'.$adult->id)->assertOk()
                ->assertJsonPath('older_adult.medications.0.dosage', '1 tableta');
            $this->getJson('/api/incidents?date=2026-10-20')->assertOk()->assertJsonCount(2, 'incidents');
            Sanctum::actingAs(User::findOrFail($professional->id));
            $this->getJson('/api/incidents?date=2026-10-20')->assertOk()
                ->assertJsonCount(1, 'incidents')->assertJsonPath('incidents.0.id', $incident->id);
            $this->getJson('/api/rutinas')->assertOk()->assertJsonCount(1, 'rutinas')
                ->assertJsonPath('rutinas.0.adulto_mayor_id', $adult->id);
            Sanctum::actingAs(User::findOrFail($family->id));
            $this->getJson('/api/family/older-adults/'.$otherAdult->id)->assertNotFound();
        } finally {
            DB::setDefaultConnection($sourceConnection);
            DB::purge('recovered_care_test');
            Carbon::setTestNow();
            if (is_string($copy) && is_file($copy)) {
                unlink($copy);
            }
        }
    }
}
