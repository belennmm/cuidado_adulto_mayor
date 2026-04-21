<?php

namespace App\Http\Controllers;

use App\Models\OlderAdult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OlderAdultController extends Controller
{
    public function show(OlderAdult $olderAdult): JsonResponse
    {
        return response()->json([
            'older_adult' => $this->formatOlderAdult($olderAdult),
        ]);
    }

    public function index(): JsonResponse
    {
        $olderAdults = OlderAdult::query()
            ->orderBy('full_name')
            ->get()
            ->map(fn (OlderAdult $olderAdult) => $this->formatOlderAdult($olderAdult));

        return response()->json(['older_adults' => $olderAdults]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $data['status'] = $data['status'] ?? 'Estable';
        $data['created_by'] = $request->user()->id;

        $olderAdult = OlderAdult::create($data);

        return response()->json([
            'message' => 'Adulto mayor creado correctamente.',
            'older_adult' => $this->formatOlderAdult($olderAdult),
        ], 201);
    }

    public function update(Request $request, OlderAdult $olderAdult): JsonResponse
    {
        $data = $request->validate($this->rules());
        $data['status'] = $data['status'] ?? 'Estable';

        $olderAdult->update($data);
        $olderAdult->refresh();

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
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:255',
            'allergies' => 'nullable|string|max:255',
            'medical_history' => 'nullable|string',
            'notes' => 'nullable|string',
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
            'emergency_contact_name' => $olderAdult->emergency_contact_name,
            'emergency_contact_phone' => $olderAdult->emergency_contact_phone,
            'allergies' => $olderAdult->allergies,
            'medical_history' => $olderAdult->medical_history,
            'notes' => $olderAdult->notes,
            'created_at' => $olderAdult->created_at?->toISOString(),
        ];
    }
}
