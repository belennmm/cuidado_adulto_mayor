<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListRequest;
use App\Support\ListPage;
use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;

class ReminderController extends Controller
{
    public function __construct(private readonly ReminderService $reminderService) {}

    public function index(ListRequest $request): JsonResponse
    {
        $data = $this->reminderService->remindersFor($request->user());

        return response()->json([
            ...$data,
            ...ListPage::payload(ListPage::collection($data['reminders'], $request->validated()), 'reminders'),
        ]);
    }
}
