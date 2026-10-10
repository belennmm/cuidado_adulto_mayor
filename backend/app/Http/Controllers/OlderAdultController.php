<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmptyInputRequest;
use App\Http\Requests\ListRequest;
use App\Support\ListPage;
use App\Http\Requests\OlderAdultRequest;
use App\Http\Resources\OlderAdultResource;
use App\Models\OlderAdult;
use App\Services\OlderAdultService;
use Illuminate\Http\JsonResponse;

class OlderAdultController extends Controller
{
    public function __construct(private readonly OlderAdultService $olderAdultService) {}

    public function show(EmptyInputRequest $request, OlderAdult $olderAdult): JsonResponse
    {
        $olderAdult = $this->olderAdultService->loadRelations($olderAdult);

        return response()->json([
            'older_adult' => OlderAdultResource::make($olderAdult)->resolve(),
        ]);
    }

    public function index(ListRequest $request): JsonResponse
    {
        $query = OlderAdult::query()
            ->with(['familyCaregiver', 'professionalCaregiver'])
            ->orderBy('full_name')->orderBy('id');
        $search = $request->validated('q');
        if ($search !== null && $search !== '') {
            $query->where(function ($query) use ($search) {
                $term = '%'.mb_strtolower($search).'%';
                $query->whereRaw("LOWER(COALESCE(full_name, '')) LIKE ? OR LOWER(COALESCE(caregiver_family, '')) LIKE ? OR LOWER(COALESCE(room, '')) LIKE ? OR LOWER(COALESCE(status, '')) LIKE ? OR COALESCE(CAST(age AS TEXT), '') LIKE ?", [$term, $term, $term, $term, $term]);
            });
        }
        $olderAdults = ListPage::query($query, $request->validated());

        return response()->json(ListPage::payload($olderAdults, 'older_adults', fn (OlderAdult $adult) => OlderAdultResource::make($adult)->resolve()));
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

    public function destroy(EmptyInputRequest $request, OlderAdult $olderAdult): JsonResponse
    {
        $olderAdult->delete();

        return response()->json([
            'message' => 'Adulto mayor eliminado correctamente.',
        ]);
    }
}
