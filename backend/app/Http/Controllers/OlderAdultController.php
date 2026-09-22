<?php

namespace App\Http\Controllers;

use App\Http\Requests\OlderAdultRequest;
use App\Http\Resources\OlderAdultResource;
use App\Models\OlderAdult;
use App\Services\OlderAdultService;
use Illuminate\Http\JsonResponse;

class OlderAdultController extends Controller
{
    public function __construct(private readonly OlderAdultService $olderAdultService) {}

    public function show(OlderAdult $olderAdult): JsonResponse
    {
        $olderAdult = $this->olderAdultService->loadRelations($olderAdult);

        return response()->json([
            'older_adult' => OlderAdultResource::make($olderAdult)->resolve(),
        ]);
    }

    public function index(): JsonResponse
    {
        $olderAdults = OlderAdult::query()
            ->with(['familyCaregiver', 'professionalCaregiver'])
            ->orderBy('full_name')
            ->get()
            ->map(fn (OlderAdult $olderAdult) => OlderAdultResource::make($olderAdult)->resolve());

        return response()->json(['older_adults' => $olderAdults]);
    }

    public function store(OlderAdultRequest $request): JsonResponse
    {
        $olderAdult = $this->olderAdultService->create($request->validated(), $request->user());

        return response()->json([
            'message' => 'Adulto mayor creado correctamente.',
            'older_adult' => OlderAdultResource::make($olderAdult)->resolve(),
        ], 201);
    }

    public function update(OlderAdultRequest $request, OlderAdult $olderAdult): JsonResponse
    {
        $olderAdult = $this->olderAdultService->update($olderAdult, $request->validated());

        return response()->json([
            'message' => 'Adulto mayor actualizado correctamente.',
            'older_adult' => OlderAdultResource::make($olderAdult)->resolve(),
        ]);
    }

    public function destroy(OlderAdult $olderAdult): JsonResponse
    {
        $olderAdult->delete();

        return response()->json([
            'message' => 'Adulto mayor eliminado correctamente.',
        ]);
    }
}
