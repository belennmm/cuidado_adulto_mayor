<?php

namespace App\Http\Controllers;

use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function __construct(private readonly ReminderService $reminderService) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->reminderService->remindersFor($request->user()),
        );
    }
}
