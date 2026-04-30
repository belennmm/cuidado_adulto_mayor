<?php

namespace App\Http\Controllers;

use App\Models\Medication;
use App\Models\OlderAdult;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class OlderAdultController extends Controller
{
    public function show(OlderAdult $olderAdult): JsonResponse
    {
        $olderAdult->load(['medicationAssignments.medication', 'familyCaregiver', 'professionalCaregiver']);

        return response()->json([
            'older_adult' => $this->formatOlderAdult($olderAdult),
        ]);
    }

    public function index(): JsonResponse
    {
        $olderAdults = OlderAdult::query()
            ->with(['familyCaregiver', 'professionalCaregiver'])
            ->orderBy('full_name')
            ->get()
            ->map(fn (OlderAdult $olderAdult) => $this->formatOlderAdult($olderAdult));

        return response()->json(['older_adults' => $olderAdults]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());
        $data = $this->normalizeCaregiverAssignments($data);
        $medications = $data['medications'] ?? [];
        unset($data['medications']);

        $data['status'] = $data['status'] ?? 'Estable';
        $data['created_by'] = $request->user()->id;

        $olderAdult = DB::transaction(function () use ($data, $medications) {
            $olderAdult = OlderAdult::create($data);
            $this->syncMedications($olderAdult, $medications);
            return $olderAdult->load(['medicationAssignments.medication', 'familyCaregiver', 'professionalCaregiver']);
        });

        return response()->json([
            'message' => 'Adulto mayor creado correctamente.',
            'older_adult' => $this->formatOlderAdult($olderAdult),
        ], 201);
    }

    public function update(Request $request, OlderAdult $olderAdult): JsonResponse
    {
        $data = $request->validate($this->rules());
        $data = $this->normalizeCaregiverAssignments($data);
        $medications = $data['medications'] ?? [];
        unset($data['medications']);
        $data['status'] = $data['status'] ?? 'Estable';

        DB::transaction(function () use ($olderAdult, $data, $medications) {
            $olderAdult->update($data);
            $this->syncMedications($olderAdult, $medications);
        });

        $olderAdult->refresh()->load(['medicationAssignments.medication', 'familyCaregiver', 'professionalCaregiver']);

        return response()->json([
            'message' => 'Adulto mayor actualizado correctamente.',
            'older_adult' => $this->formatOlderAdult($olderAdult),
        ]);
    }

    public function destroy(OlderAdult $olderAdult): JsonResponse
    {
        $olderAdult->delete();

        return response()->json([
            'message' => 'Adulto mayor eliminado correctamente.',
        ]);
    }

    private function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'age' => 'nullable|integer|min:0|max:130',
            'birthdate' => 'nullable|date',
            'gender' => 'nullable|string|max:255',
            'room' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:255',
            'caregiver_family' => 'nullable|string|max:255',
            'family_caregiver_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', 'familiar')
                    ->where('is_approved', true)),
            ],
            'professional_caregiver_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', 'profesional')
                    ->where('is_approved', true)),
            ],
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:255',
            'allergies' => 'nullable|string|max:255',
            'medical_history' => 'nullable|string',
            'notes' => 'nullable|string',
            'medications' => 'nullable|array',
            'medications.*.name' => 'required|string|max:255',
            'medications.*.dosage' => 'nullable|string|max:255',
            'medications.*.schedule' => 'nullable|string|max:255',
            'medications.*.days' => 'nullable|array',
            'medications.*.days.*' => 'string|max:50',
            'medications.*.notes' => 'nullable|string',
        ];
    }

    private function formatOlderAdult(OlderAdult $olderAdult): array
    {
        return [
            'id' => $olderAdult->id,
            'full_name' => $olderAdult->full_name,
            'age' => $olderAdult->age,
            'birthdate' => $olderAdult->birthdate?->toDateString(),
            'gender' => $olderAdult->gender,
            'room' => $olderAdult->room,
            'status' => $olderAdult->status,
            'caregiver_family' => $olderAdult->caregiver_family,
            'family_caregiver_id' => $olderAdult->family_caregiver_id,
            'family_caregiver_name' => $olderAdult->familyCaregiver?->name,
            'professional_caregiver_id' => $olderAdult->professional_caregiver_id,
            'professional_caregiver_name' => $olderAdult->professionalCaregiver?->name,
            'emergency_contact_name' => $olderAdult->emergency_contact_name,
            'emergency_contact_phone' => $olderAdult->emergency_contact_phone,
            'allergies' => $olderAdult->allergies,
            'medical_history' => $olderAdult->medical_history,
            'notes' => $olderAdult->notes,
            'medications' => $olderAdult->relationLoaded('medicationAssignments')
                ? $olderAdult->medicationAssignments->map(fn ($assignment) => [
                    'id' => $assignment->id,
                    'medication_id' => $assignment->medication_id,
                    'name' => $assignment->medication?->name,
                    'dosage' => $assignment->dosage,
                    'schedule' => $assignment->schedule,
                    'days' => $assignment->days ?? [],
                    'notes' => $assignment->notes,
                    'is_active' => $assignment->is_active,
                ])->values()->all()
                : [],
            'created_at' => $olderAdult->created_at?->toISOString(),
        ];
    }

    private function normalizeCaregiverAssignments(array $data): array
    {
        $familyCaregiverId = $data['family_caregiver_id'] ?? null;
        $familyCaregiver = null;

        if ($familyCaregiverId) {
            $familyCaregiver = User::query()
                ->where('role', 'familiar')
                ->where('is_approved', true)
                ->find($familyCaregiverId);
        } elseif (!empty($data['caregiver_family'])) {
            $familyCaregiver = User::query()
                ->where('role', 'familiar')
                ->where('is_approved', true)
                ->whereRaw('LOWER(name) = ?', [strtolower((string) $data['caregiver_family'])])
                ->first();
        }

        $data['family_caregiver_id'] = $familyCaregiver?->id;
        $data['caregiver_family'] = $familyCaregiver?->name;

        return $data;
    }

    private function syncMedications(OlderAdult $olderAdult, array $medications): void
    {
        $olderAdult->medicationAssignments()->delete();

        foreach ($medications as $medicationData) {
            $name = trim((string) ($medicationData['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $medication = Medication::firstOrCreate(
                ['name' => $name],
                ['is_active' => true]
            );

            $olderAdult->medicationAssignments()->create([
                'medication_id' => $medication->id,
                'dosage' => $this->nullableString($medicationData['dosage'] ?? null),
                'schedule' => $this->nullableString($medicationData['schedule'] ?? null),
                'days' => $this->normalizeDays($medicationData['days'] ?? []),
                'notes' => $this->nullableString($medicationData['notes'] ?? null),
                'is_active' => true,
            ]);
        }
    }

    private function normalizeDays(mixed $days): array
    {
        if (!is_array($days)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($day) {
            $value = trim((string) $day);
            return $value !== '' ? $value : null;
        }, $days)));
    }

    private function nullableString(mixed $value): ?string
    {
        $stringValue = trim((string) ($value ?? ''));
        return $stringValue !== '' ? $stringValue : null;
    }
}
