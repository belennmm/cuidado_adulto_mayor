<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmptyInputRequest;
use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;

class ReminderController extends Controller
{
    public function __construct(private readonly ReminderService $reminderService) {}

    public function index(EmptyInputRequest $request): JsonResponse
    {
        return response()->json(
            $this->reminderService->remindersFor($request->user()),
        );
    }
}
