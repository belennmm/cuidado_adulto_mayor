<?php

namespace Database\Seeders;

use App\Models\CaregiverSchedule;
use App\Models\Incident;
use App\Models\Medication;
use App\Models\MedicationAdministration;
use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
use App\Models\RoutineNote;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class InitialDataSeeder extends Seeder
{
    public function run(): void
    {
        $users = $this->seedUsers();
        $olderAdults = $this->seedOlderAdults($users['admin'], $users);
        $this->assignProfessionalCaregivers($olderAdults, $users);
        $this->seedSchedules($users);
        $this->seedMedications($olderAdults, $users);
        $this->seedIncidents($users, $olderAdults);
        $this->seedRoutineNotes($users, $olderAdults);
    }

    private function seedUsers(): array
    {
        $users = [
            'admin' => User::updateOrCreate(
                ['email' => 'belen@gmail.com'],
                [
                    'name' => 'Belen Admin',
                    'password' => 'belen123',
                    'role' => 'admin',
                    'is_approved' => true,
                    'location' => 'Guatemala',
                    'phone' => '5555-0101',
                    'birthdate' => '1998-05-16',
                ]
            ),
            'admin_2' => User::updateOrCreate(
                ['email' => 'mon231497@uvg.edu.gt'],
                [
                    'name' => 'Mon Admin',
                    'password' => 'admin123',
                    'role' => 'admin',
                    'is_approved' => true,
                    'location' => 'Guatemala',
                    'phone' => '5555-0107',
                    'birthdate' => '1999-04-23',
                ]
            ),
            'admin_her' => User::updateOrCreate(
                ['email' => 'her241424@uvg.edu.gt'],
                [
                    'name' => 'wichandro',
                    'password' => 'admin123',
                    'role' => 'admin',
                    'is_approved' => true,
                    'location' => 'guatemala',
                    'phone' => '12345678',
                    'birthdate' => '2005-05-17',
                ]
            ),
            'admin_wicho' => User::updateOrCreate(
                ['email' => 'wicho123@gmail.com'],
                [
                    'name' => 'Wicho Admin',
                    'password' => 'wicho123',
                    'role' => 'admin',
                    'is_approved' => true,
                    'location' => 'Guatemala',
                ]
            ),
            'professional_1' => User::updateOrCreate(
                ['email' => 'maria.gonzalez@organizate.com'],
                [
                    'name' => 'Maria Gonzalez',
                    'password' => 'cuidador123',
                    'role' => 'profesional',
                    'is_approved' => true,
                    'location' => 'Zona 10',
                    'phone' => '5555-0102',
                    'birthdate' => '1992-03-11',
                ]
            ),
            'professional_2' => User::updateOrCreate(
                ['email' => 'daniel.soto@organizate.com'],
                [
                    'name' => 'Daniel Soto',
                    'password' => 'cuidador123',
                    'role' => 'profesional',
                    'is_approved' => true,
                    'location' => 'Mixco',
                    'phone' => '5555-0103',
                    'birthdate' => '1990-09-22',
                ]
            ),
            'family_1' => User::updateOrCreate(
                ['email' => 'laura.rodriguez@familia.com'],
                [
                    'name' => 'Laura Rodriguez',
                    'password' => 'familiar123',
                    'role' => 'familiar',
                    'is_approved' => true,
                    'location' => 'Villa Nueva',
                    'phone' => '5555-0104',
                    'birthdate' => '1987-01-30',
                ]
            ),
            'family_2' => User::updateOrCreate(
                ['email' => 'jose.perez@familia.com'],
                [
                    'name' => 'Jose Perez',
                    'password' => 'familiar123',
                    'role' => 'familiar',
                    'is_approved' => false,
                    'location' => 'Antigua Guatemala',
                    'phone' => '5555-0105',
                    'birthdate' => '1985-07-14',
                ]
            ),
            'family_3' => User::updateOrCreate(
                ['email' => 'ana.lopez@familia.com'],
                [
                    'name' => 'Ana Lopez',
                    'password' => 'familiar123',
                    'role' => 'familiar',
                    'is_approved' => true,
                    'location' => 'Santa Catarina Pinula',
                    'phone' => '5555-0106',
                    'birthdate' => '1991-11-08',
                ]
            ),
        ];

        return $users;
    }

    private function seedOlderAdults(User $admin, array $users): array
    {
        $olderAdults = [
            [
                'full_name' => 'Rosa Martinez',
                'age' => 81,
                'birthdate' => '1944-02-12',
                'gender' => 'Femenino',
                'room' => 'A-101',
                'status' => 'Estable',
                'caregiver_family' => 'Laura Rodriguez',
                'family_caregiver_id' => $users['family_1']->id,
                'emergency_contact_name' => 'Carolina Martinez',
                'emergency_contact_phone' => '5555-2101',
                'allergies' => 'Penicilina',
                'medical_history' => 'Hipertension controlada y seguimiento cardiologico.',
                'notes' => 'Requiere apoyo para desplazamientos largos.',
                'created_by' => $admin->id,
            ],
            [
                'full_name' => 'Miguel Herrera',
                'age' => 76,
                'birthdate' => '1949-08-04',
                'gender' => 'Masculino',
                'room' => 'B-204',
                'status' => 'Atencion',
                'caregiver_family' => null,
                'family_caregiver_id' => null,
                'emergency_contact_name' => 'Lucia Herrera',
                'emergency_contact_phone' => '5555-2102',
                'allergies' => 'Ninguna registrada',
                'medical_history' => 'Diabetes tipo 2 y control de glucosa diario.',
                'notes' => 'Necesita supervision en horarios de alimentacion.',
                'created_by' => $admin->id,
            ],
            [
                'full_name' => 'Elena Castillo',
                'age' => 88,
                'birthdate' => '1937-12-19',
                'gender' => 'Femenino',
                'room' => 'C-305',
                'status' => 'Critico',
                'caregiver_family' => 'Ana Lopez',
                'family_caregiver_id' => $users['family_3']->id,
                'emergency_contact_name' => 'Sofia Castillo',
                'emergency_contact_phone' => '5555-2103',
                'allergies' => 'Mariscos',
                'medical_history' => 'Antecedente de EPOC y monitoreo respiratorio frecuente.',
                'notes' => 'Mantener observacion constante durante la noche.',
                'created_by' => $admin->id,
            ],
            [
                'full_name' => 'Carlos Ramirez',
                'age' => 79,
                'birthdate' => '1946-05-01',
                'gender' => 'Masculino',
                'room' => 'A-103',
                'status' => 'Estable',
                'caregiver_family' => 'Laura Rodriguez',
                'family_caregiver_id' => $users['family_1']->id,
                'emergency_contact_name' => 'Pablo Ramirez',
                'emergency_contact_phone' => '5555-2104',
                'allergies' => 'Sulfas',
                'medical_history' => 'Artritis y terapia fisica dos veces por semana.',
                'notes' => 'Buen animo, participa en actividades grupales.',
                'created_by' => $admin->id,
            ],
        ];

        $savedOlderAdults = [];

        foreach ($olderAdults as $olderAdult) {
            $saved = OlderAdult::updateOrCreate(
                ['full_name' => $olderAdult['full_name']],
                $olderAdult
            );

            $savedOlderAdults[$saved->full_name] = $saved;
        }

        return $savedOlderAdults;
    }

    private function assignProfessionalCaregivers(array $olderAdults, array $users): void
    {
        $assignments = [
            'Rosa Martinez' => $users['professional_1']->id,
            'Miguel Herrera' => $users['professional_1']->id,
            'Elena Castillo' => $users['professional_2']->id,
            'Carlos Ramirez' => $users['professional_2']->id,
        ];

        foreach ($assignments as $olderAdultName => $caregiverId) {
            $olderAdult = $olderAdults[$olderAdultName] ?? null;

            if (!$olderAdult) {
                continue;
            }

            $olderAdult->update([
                'professional_caregiver_id' => $caregiverId,
            ]);
        }
    }

    private function seedSchedules(array $users): void
    {
        $today = Carbon::today();
        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);

        $schedules = [
            [
                'user_id' => $users['professional_1']->id,
                'day_of_week' => 1,
                'start_time' => '07:00',
                'end_time' => '15:00',
                'notes' => 'Turno matutino de seguimiento general.',
                'change_request_status' => null,
                'change_request_start_time' => null,
                'change_request_end_time' => null,
                'change_request_notes' => null,
                'change_request_message' => null,
            ],
            [
                'user_id' => $users['professional_1']->id,
                'day_of_week' => 4,
                'start_time' => '08:00',
                'end_time' => '16:00',
                'notes' => 'Cobertura de rutina y medicamentos.',
                'change_request_status' => 'pending',
                'change_request_start_time' => '09:00',
                'change_request_end_time' => '17:00',
                'change_request_notes' => 'Solicita mover el turno una hora por cita medica.',
                'change_request_message' => 'Cambio solicitado por cita medica programada.',
            ],
            [
                'user_id' => $users['professional_2']->id,
                'day_of_week' => 3,
                'start_time' => '06:00',
                'end_time' => '14:00',
                'notes' => 'Supervision temprana y control respiratorio.',
                'change_request_status' => null,
                'change_request_start_time' => null,
                'change_request_end_time' => null,
                'change_request_notes' => null,
                'change_request_message' => null,
            ],
            [
                'user_id' => $users['professional_2']->id,
                'day_of_week' => 6,
                'start_time' => '10:00',
                'end_time' => '18:00',
                'notes' => 'Apoyo de fin de semana y seguimiento de actividades.',
                'change_request_status' => null,
                'change_request_start_time' => null,
                'change_request_end_time' => null,
                'change_request_notes' => null,
                'change_request_message' => null,
            ],
        ];

        foreach ($schedules as $schedule) {
            CaregiverSchedule::updateOrCreate(
                [
                    'user_id' => $schedule['user_id'],
                    'day_of_week' => $schedule['day_of_week'],
                ],
                $schedule
            );
        }

        VacationRequest::updateOrCreate(
            [
                'user_id' => $users['professional_2']->id,
                'start_date' => $weekStart->copy()->addDays(2)->toDateString(),
                'end_date' => $weekStart->copy()->addDays(2)->toDateString(),
            ],
            [
                'reason' => 'Permiso aprobado para cita medica de seguimiento.',
                'status' => 'approved',
                'reviewed_by' => $users['admin']->id,
                'reviewed_at' => $today->copy()->setTime(9, 0),
            ]
        );
    }

    private function seedMedications(array $olderAdults, array $users): void
    {
        $catalog = [
            [
                'name' => 'Losartan',
                'presentation' => 'Caja de tabletas 50 mg',
                'quantity' => 120,
                'unit' => 'tabletas',
                'minimum_stock' => 30,
                'expiration_date' => Carbon::today()->copy()->addMonths(9)->toDateString(),
            ],
            [
                'name' => 'Metformina',
                'presentation' => 'Caja de tabletas 850 mg',
                'quantity' => 26,
                'unit' => 'tabletas',
                'minimum_stock' => 30,
                'expiration_date' => Carbon::today()->copy()->addMonths(4)->toDateString(),
            ],
            [
                'name' => 'Salbutamol',
                'presentation' => 'Inhalador 100 mcg',
                'quantity' => 15,
                'unit' => 'inhaladores',
                'minimum_stock' => 8,
                'expiration_date' => Carbon::today()->copy()->addDays(18)->toDateString(),
            ],
            [
                'name' => 'Paracetamol',
                'presentation' => 'Blister de tabletas 500 mg',
                'quantity' => 88,
                'unit' => 'tabletas',
                'minimum_stock' => 25,
                'expiration_date' => Carbon::today()->copy()->addMonths(6)->toDateString(),
            ],
            [
                'name' => 'Ibuprofeno',
                'presentation' => 'Frasco de tabletas 400 mg',
                'quantity' => 9,
                'unit' => 'tabletas',
                'minimum_stock' => 12,
                'expiration_date' => Carbon::today()->copy()->subDays(3)->toDateString(),
            ],
            [
                'name' => 'Omeprazol',
                'presentation' => 'Caja de capsulas 20 mg',
                'quantity' => 42,
                'unit' => 'capsulas',
                'minimum_stock' => 15,
                'expiration_date' => Carbon::today()->copy()->addMonths(7)->toDateString(),
            ],
        ];

        foreach ($catalog as $medicationData) {
            Medication::updateOrCreate(
                ['name' => $medicationData['name']],
                [
                    'presentation' => $medicationData['presentation'],
                    'quantity' => $medicationData['quantity'],
                    'unit' => $medicationData['unit'],
                    'minimum_stock' => $medicationData['minimum_stock'],
                    'expiration_date' => $medicationData['expiration_date'],
                    'is_active' => true,
                ]
            );
        }

        $assignments = [
            'Rosa Martinez' => [
                [
                    'name' => 'Losartan',
                    'dosage' => '1 tableta',
                    'schedule' => '8:00 AM',
                    'days' => ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'],
                    'notes' => 'Administrar despues del desayuno.',
                ],
                [
                    'name' => 'Omeprazol',
                    'dosage' => '1 capsula',
                    'schedule' => '7:00 AM',
                    'days' => ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'],
                    'notes' => 'Administrar 30 minutos antes del desayuno.',
                ],
            ],
            'Miguel Herrera' => [
                [
                    'name' => 'Metformina',
                    'dosage' => '1 tableta',
                    'schedule' => '8:00 AM y 8:00 PM',
                    'days' => ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'],
                    'notes' => 'Verificar glicemia antes de la dosis matutina.',
                ],
            ],
            'Elena Castillo' => [
                [
                    'name' => 'Salbutamol',
                    'dosage' => '2 inhalaciones',
                    'schedule' => '6:00 AM, 2:00 PM, 10:00 PM',
                    'days' => ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo'],
                    'notes' => 'Usar con supervision del personal.',
                ],
            ],
            'Carlos Ramirez' => [
                [
                    'name' => 'Paracetamol',
                    'dosage' => '1 tableta',
                    'schedule' => '9:00 AM',
                    'days' => ['lunes', 'miercoles', 'viernes'],
                    'notes' => 'Solo en dias de terapia fisica.',
                ],
            ],
        ];

        foreach ($assignments as $olderAdultName => $medications) {
            $olderAdult = $olderAdults[$olderAdultName] ?? null;

            if (!$olderAdult) {
                continue;
            }

            $olderAdult->medicationAssignments()->delete();

            foreach ($medications as $assignment) {
                $medication = Medication::where('name', $assignment['name'])->first();

                if (!$medication) {
                    continue;
                }

                OlderAdultMedication::updateOrCreate(
                    [
                        'older_adult_id' => $olderAdult->id,
                        'medication_id' => $medication->id,
                    ],
                    [
                        'dosage' => $assignment['dosage'],
                        'schedule' => $assignment['schedule'],
                        'days' => $assignment['days'],
                        'notes' => $assignment['notes'],
                        'is_active' => true,
                    ]
                );
            }
        }

        $scheduledLogs = [
            [
                'older_adult' => 'Rosa Martinez',
                'medication' => 'Losartan',
                'administration_type' => 'scheduled',
                'dosage' => '1 tableta',
                'administration_date' => Carbon::today()->toDateString(),
                'administration_time' => '08:10:00',
                'notes' => 'Administracion registrada manualmente para dashboard.',
                'recorded_by' => $users['professional_1']->id,
            ],
            [
                'older_adult' => 'Miguel Herrera',
                'medication' => 'Metformina',
                'administration_type' => 'scheduled',
                'dosage' => '1 tableta',
                'administration_date' => Carbon::today()->toDateString(),
                'administration_time' => '08:05:00',
                'notes' => 'Administracion registrada manualmente para dashboard.',
                'recorded_by' => $users['professional_1']->id,
            ],
        ];

        foreach ($scheduledLogs as $log) {
            $olderAdult = $olderAdults[$log['older_adult']] ?? null;
            $medication = Medication::where('name', $log['medication'])->first();

            if (!$olderAdult || !$medication) {
                continue;
            }

            $assignment = OlderAdultMedication::query()
                ->where('older_adult_id', $olderAdult->id)
                ->where('medication_id', $medication->id)
                ->first();

            if (!$assignment) {
                continue;
            }

            MedicationAdministration::updateOrCreate(
                [
                    'older_adult_id' => $olderAdult->id,
                    'older_adult_medication_id' => $assignment->id,
                    'medication_id' => $medication->id,
                    'administration_type' => $log['administration_type'],
                    'administration_date' => $log['administration_date'],
                    'administration_time' => $log['administration_time'],
                ],
                [
                    'dosage' => $log['dosage'],
                    'notes' => $log['notes'],
                    'recorded_by' => $log['recorded_by'],
                ]
            );
        }

        $additionalLogs = [
            [
                'older_adult' => 'Miguel Herrera',
                'medication' => 'Paracetamol',
                'administration_type' => 'additional',
                'dosage' => '1 tableta',
                'administration_date' => Carbon::today()->toDateString(),
                'administration_time' => '01:45:00',
                'notes' => 'Administrada por dolor de cabeza reportado despues del almuerzo.',
                'recorded_by' => $users['professional_1']->id,
            ],
            [
                'older_adult' => 'Elena Castillo',
                'medication' => 'Ibuprofeno',
                'administration_type' => 'additional',
                'dosage' => '1 tableta',
                'administration_date' => Carbon::today()->toDateString(),
                'administration_time' => '09:20:00',
                'notes' => 'Administrada por inflamacion leve bajo indicacion medica.',
                'recorded_by' => $users['professional_2']->id,
            ],
        ];

        foreach ($additionalLogs as $log) {
            $olderAdult = $olderAdults[$log['older_adult']] ?? null;
            $medication = Medication::where('name', $log['medication'])->first();

            if (!$olderAdult || !$medication) {
                continue;
            }

            MedicationAdministration::updateOrCreate(
                [
                    'older_adult_id' => $olderAdult->id,
                    'medication_id' => $medication->id,
                    'administration_type' => $log['administration_type'],
                    'administration_date' => $log['administration_date'],
                    'administration_time' => $log['administration_time'],
                ],
                [
                    'dosage' => $log['dosage'],
                    'notes' => $log['notes'],
                    'recorded_by' => $log['recorded_by'],
                ]
            );
        }
    }

    private function seedIncidents(array $users, array $olderAdults): void
    {
        $today = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        $incidents = [
            [
                'title' => 'Control de glucosa fuera de rango',
                'description' => 'Se detecto una lectura elevada antes del almuerzo y se notifico al equipo.',
                'adult_name' => 'Miguel Herrera',
                'older_adult_id' => $olderAdults['Miguel Herrera']->id ?? null,
                'severity' => 'alta',
                'status' => 'en seguimiento',
                'incident_date' => $today,
                'incident_time' => '11:30:00',
                'reported_by' => $users['professional_1']->id,
            ],
            [
                'title' => 'Molestia respiratoria nocturna',
                'description' => 'Se apoyo con oxigenacion y monitoreo hasta estabilizar signos.',
                'adult_name' => 'Elena Castillo',
                'older_adult_id' => $olderAdults['Elena Castillo']->id ?? null,
                'severity' => 'alta',
                'status' => 'abierto',
                'incident_date' => $today,
                'incident_time' => '03:15:00',
                'reported_by' => $users['professional_2']->id,
            ],
            [
                'title' => 'Caida leve sin lesion',
                'description' => 'El residente perdio el equilibrio al levantarse. No presenta lesion visible.',
                'adult_name' => 'Carlos Ramirez',
                'older_adult_id' => $olderAdults['Carlos Ramirez']->id ?? null,
                'severity' => 'media',
                'status' => 'cerrado',
                'incident_date' => $yesterday,
                'incident_time' => '18:20:00',
                'reported_by' => $users['admin']->id,
            ],
        ];

        foreach ($incidents as $incident) {
            Incident::updateOrCreate(
                [
                    'title' => $incident['title'],
                    'adult_name' => $incident['adult_name'],
                    'incident_date' => $incident['incident_date'],
                ],
                $incident
            );
        }
    }

    private function seedRoutineNotes(array $users, array $olderAdults): void
    {
        $today = Carbon::today();

        $notes = [
            [
                'older_adult' => 'Rosa Martinez',
                'professional' => 'professional_1',
                'content' => 'Se mantuvo estable durante la manana y colaboro bien en la rutina de medicamentos.',
                'note_date' => $today->copy()->startOfWeek(Carbon::MONDAY)->addDay()->toDateString(),
            ],
            [
                'older_adult' => 'Rosa Martinez',
                'professional' => 'professional_1',
                'content' => 'Se observo mejor apetito en el almuerzo y buena hidratacion.',
                'note_date' => $today->copy()->startOfWeek(Carbon::MONDAY)->addDays(3)->toDateString(),
            ],
            [
                'older_adult' => 'Elena Castillo',
                'professional' => 'professional_2',
                'content' => 'Necesito supervision adicional durante la noche, pero descanso mejor despues del monitoreo.',
                'note_date' => $today->copy()->startOfWeek(Carbon::MONDAY)->toDateString(),
            ],
        ];

        foreach ($notes as $note) {
            $olderAdult = $olderAdults[$note['older_adult']] ?? null;
            $professional = $users[$note['professional']] ?? null;

            if (!$olderAdult || !$professional) {
                continue;
            }

            RoutineNote::updateOrCreate(
                [
                    'older_adult_id' => $olderAdult->id,
                    'professional_caregiver_id' => $professional->id,
                    'note_date' => $note['note_date'],
                ],
                [
                    'content' => $note['content'],
                ]
            );
        }
    }
}
