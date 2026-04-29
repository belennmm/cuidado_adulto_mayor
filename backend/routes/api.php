<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\OlderAdultController;

Route::get('/ping', function () {
    return response()->json(['ok' => true]);
});

Route::get('/dashboard-summary', [AdminDashboardController::class, 'summary']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/users', [AdminUserController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/incidents/today', [IncidentController::class, 'today']);
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard-summary', [AdminDashboardController::class, 'summary']);
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/professional-caregivers', [AdminUserController::class, 'professionalCaregivers']);
    Route::get('/users/{user}', [AdminUserController::class, 'show']);
    Route::put('/users/{user}', [AdminUserController::class, 'update']);
    Route::patch('/users/{user}/approve', [AdminUserController::class, 'approve']);
    Route::delete('/users/{user}/reject', [AdminUserController::class, 'reject']);
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
    Route::get('/older-adults', [OlderAdultController::class, 'index']);
    Route::post('/older-adults', [OlderAdultController::class, 'store']);
    Route::get('/older-adults/{olderAdult}', [OlderAdultController::class, 'show']);
    Route::put('/older-adults/{olderAdult}', [OlderAdultController::class, 'update']);
    Route::delete('/older-adults/{olderAdult}', [OlderAdultController::class, 'destroy']);
});
