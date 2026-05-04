<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaregiverScheduleController;
use App\Http\Controllers\FamilyCareController;
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
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateMe']);
    Route::get('/incidents', [IncidentController::class, 'index']);
    Route::get('/incidents/today', [IncidentController::class, 'today']);

    Route::post('/schedules', [CaregiverScheduleController::class, 'store']);
    Route::put('/schedules/{schedule}', [CaregiverScheduleController::class, 'update']);

    Route::prefix('family')->group(function () {
        Route::get('/overview', [FamilyCareController::class, 'overview']);
        Route::get('/older-adults', [FamilyCareController::class, 'olderAdults']);
        Route::get('/older-adults/{olderAdult}', [FamilyCareController::class, 'olderAdult']);
        Route::get('/incidents', [FamilyCareController::class, 'incidents']);
        Route::get('/routine', [FamilyCareController::class, 'routine']);
        Route::get('/routines', [FamilyCareController::class, 'routine']);
    });
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard-summary', [AdminDashboardController::class, 'summary']);
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::get('/professional-caregivers', [AdminUserController::class, 'professionalCaregivers']);
    Route::get('/family-caregivers', [AdminUserController::class, 'familyCaregivers']);
    Route::get('/users/{user}', [AdminUserController::class, 'show']);
    Route::put('/users/{user}', [AdminUserController::class, 'update']);
    Route::patch('/users/{user}/approve', [AdminUserController::class, 'approve']);
    Route::delete('/users/{user}/reject', [AdminUserController::class, 'reject']);
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
    Route::get('/schedules', [CaregiverScheduleController::class, 'adminIndex']);
    Route::post('/schedules', [CaregiverScheduleController::class, 'adminStore']);
    Route::delete('/schedules/{schedule}', [CaregiverScheduleController::class, 'destroy']);
    Route::get('/older-adults', [OlderAdultController::class, 'index']);
    Route::post('/older-adults', [OlderAdultController::class, 'store']);
    Route::get('/older-adults/{olderAdult}', [OlderAdultController::class, 'show']);
    Route::put('/older-adults/{olderAdult}', [OlderAdultController::class, 'update']);
    Route::delete('/older-adults/{olderAdult}', [OlderAdultController::class, 'destroy']);
});
