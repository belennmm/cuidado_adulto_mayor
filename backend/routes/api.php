<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaregiverScheduleController;
use App\Http\Controllers\FamilyCareController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\MedicationAdministrationController;
use App\Http\Controllers\MedicationInventoryController;
use App\Http\Controllers\MobilityExerciseController;
use App\Http\Controllers\OlderAdultController;
use App\Http\Controllers\ProfessionalCareController;
use App\Http\Controllers\ProfessionalIncidentController;
use App\Http\Controllers\ProfessionalRoutineNoteController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\RutinaController;
use App\Http\Controllers\VacationRequestController;
use App\Models\CaregiverSchedule;
use App\Models\Incident;
use App\Models\MobilityExercise;
use App\Models\OlderAdult;
use App\Models\OlderAdultMedication;
use App\Models\RoutineNote;
use App\Models\Rutina;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Support\Facades\Route;

// Public API: health probe, login and registration only.
Route::get('/ping', function () {
    return response()->json(['ok' => true]);
})->name('api.ping');

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:registration');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateMe']);
    Route::get('/incidents', [IncidentController::class, 'index'])->middleware('role:admin,profesional,familiar')->can('viewAny', Incident::class);
    Route::get('/incidents/today', [IncidentController::class, 'today'])->middleware('role:admin,profesional,familiar')->can('viewAny', Incident::class);

    Route::post('/medications/{assignment}/taken', [MedicationAdministrationController::class, 'markTaken'])->middleware('role:profesional')->can('markTaken', 'assignment');

    Route::post('/schedules', [CaregiverScheduleController::class, 'store'])->middleware('role:profesional')->can('create', CaregiverSchedule::class);
    Route::put('/schedules/{schedule}', [CaregiverScheduleController::class, 'update'])->middleware('role:admin,profesional')->can('update', 'schedule');
    Route::post('/schedules/{schedule}/change-request', [CaregiverScheduleController::class, 'requestChange'])->middleware('role:profesional')->can('requestChange', 'schedule');
    Route::middleware('role:admin,profesional,familiar')->group(function () {
        Route::get('/rutinas', [RutinaController::class, 'index'])->can('viewAny', Rutina::class);
        Route::post('/rutinas', [RutinaController::class, 'store'])->can('create', Rutina::class);
        Route::patch('/rutinas/{rutina}/completar', [RutinaController::class, 'complete'])->can('complete', 'rutina');
        Route::put('/rutinas/{rutina}', [RutinaController::class, 'update'])->can('update', 'rutina');
        Route::delete('/rutinas/{rutina}', [RutinaController::class, 'destroy'])->can('delete', 'rutina');
    });
    Route::get('/mobility-exercises', [MobilityExerciseController::class, 'index'])->middleware('role:admin,profesional')->can('viewAny', MobilityExercise::class);
    Route::get('/mobility-exercises/{mobilityExercise}', [MobilityExerciseController::class, 'show'])->middleware('role:admin,profesional')->can('view', 'mobilityExercise');

    Route::middleware('role:familiar')->prefix('family')->group(function () {
        Route::get('/overview', [FamilyCareController::class, 'overview'])->can('viewAny', OlderAdult::class);
        Route::get('/older-adults', [FamilyCareController::class, 'olderAdults'])->can('viewAny', OlderAdult::class);
        Route::get('/older-adults/{olderAdult}', [FamilyCareController::class, 'olderAdult'])->can('view', 'olderAdult');
        Route::get('/older-adults/{olderAdult}/incidents', [FamilyCareController::class, 'olderAdultIncidents'])->can('view', 'olderAdult');
        Route::get('/incidents', [FamilyCareController::class, 'incidents'])->can('viewAny', Incident::class);
        Route::get('/routine', [FamilyCareController::class, 'routine'])->can('viewAny', Rutina::class);
        Route::get('/routines', [FamilyCareController::class, 'routine'])->can('viewAny', Rutina::class);
    });

    Route::middleware('role:profesional')->prefix('professional')->group(function () {
        Route::get('/overview', [ProfessionalCareController::class, 'overview'])->can('viewAny', OlderAdult::class);
        Route::get('/older-adults', [ProfessionalCareController::class, 'olderAdults'])->can('viewAny', OlderAdult::class);
        Route::get('/older-adults/{olderAdult}', [ProfessionalCareController::class, 'olderAdult'])->can('view', 'olderAdult');
        Route::post('/incidents', [ProfessionalIncidentController::class, 'store'])->can('create', Incident::class);
        Route::patch('/incidents/{incident}', [ProfessionalIncidentController::class, 'update'])->can('update', 'incident');
        Route::get('/routines', [ProfessionalCareController::class, 'routine'])->can('viewAny', Rutina::class);
        Route::get('/reminders', [ReminderController::class, 'index'])->can('reminders', OlderAdultMedication::class);
        Route::get('/routine-notes', [ProfessionalRoutineNoteController::class, 'index'])->can('viewAny', RoutineNote::class);
        Route::post('/routine-notes', [ProfessionalRoutineNoteController::class, 'store'])->can('create', RoutineNote::class);
        Route::get('/routine-notes/{routineNote}', [ProfessionalRoutineNoteController::class, 'show'])->can('view', 'routineNote');
        Route::put('/routine-notes/{routineNote}', [ProfessionalRoutineNoteController::class, 'update'])->can('update', 'routineNote');
        Route::delete('/routine-notes/{routineNote}', [ProfessionalRoutineNoteController::class, 'destroy'])->can('delete', 'routineNote');
        Route::get('/schedules', [ProfessionalCareController::class, 'schedules'])->can('viewAny', CaregiverSchedule::class);
        Route::get('/vacation-requests', [VacationRequestController::class, 'index'])->can('viewAny', VacationRequest::class);
        Route::post('/vacation-requests', [VacationRequestController::class, 'store'])->can('create', VacationRequest::class);
    });
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard-summary', [AdminDashboardController::class, 'summary'])->can('viewAny', User::class);
    Route::get('/users', [AdminUserController::class, 'index'])->can('viewAny', User::class);
    Route::post('/users', [AdminUserController::class, 'store'])->can('create', User::class);
    Route::get('/professional-caregivers', [AdminUserController::class, 'professionalCaregivers'])->can('viewAny', User::class);
    Route::get('/family-caregivers', [AdminUserController::class, 'familyCaregivers'])->can('viewAny', User::class);
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->can('view', 'user');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->can('update', 'user');
    Route::patch('/users/{user}/approve', [AdminUserController::class, 'approve'])->can('approve', 'user');
    Route::delete('/users/{user}/reject', [AdminUserController::class, 'reject'])->can('reject', 'user');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->can('delete', 'user');
    Route::get('/schedules', [CaregiverScheduleController::class, 'adminIndex'])->can('manage', CaregiverSchedule::class);
    Route::get('/schedules/calendar', [CaregiverScheduleController::class, 'calendar'])->can('manage', CaregiverSchedule::class);
    Route::post('/schedules', [CaregiverScheduleController::class, 'adminStore'])->can('manage', CaregiverSchedule::class);
    Route::patch('/schedules/{schedule}/change-request/approve', [CaregiverScheduleController::class, 'approveChangeRequest'])->can('review', 'schedule');
    Route::patch('/schedules/{schedule}/change-request/reject', [CaregiverScheduleController::class, 'rejectChangeRequest'])->can('review', 'schedule');
    Route::delete('/schedules/{schedule}', [CaregiverScheduleController::class, 'destroy'])->can('delete', 'schedule');
    Route::get('/vacation-requests', [VacationRequestController::class, 'adminIndex'])->can('manage', VacationRequest::class);
    Route::patch('/vacation-requests/{vacationRequest}/approve', [VacationRequestController::class, 'approve'])->can('review', 'vacationRequest');
    Route::patch('/vacation-requests/{vacationRequest}/reject', [VacationRequestController::class, 'reject'])->can('review', 'vacationRequest');
    Route::get('/medication-statistics', [AdminDashboardController::class, 'medicationStatistics'])->can('viewAny', OlderAdultMedication::class);
    Route::get('/medications/inventory', [MedicationInventoryController::class, 'index'])->can('viewAny', OlderAdultMedication::class);
    Route::post('/medications/inventory', [MedicationInventoryController::class, 'store'])->can('create', OlderAdultMedication::class);
    Route::put('/medications/inventory/{inventoryItem}', [MedicationInventoryController::class, 'update'])->can('update', 'inventoryItem');
    Route::patch('/medications/inventory/{inventoryItem}/stock', [MedicationInventoryController::class, 'adjustStock'])->can('adjustStock', 'inventoryItem');
    Route::delete('/medications/inventory/{inventoryItem}', [MedicationInventoryController::class, 'destroy'])->can('delete', 'inventoryItem');
    Route::post('/incidents', [ProfessionalIncidentController::class, 'store'])->can('create', Incident::class);
    Route::get('/older-adults', [OlderAdultController::class, 'index'])->can('viewAny', OlderAdult::class);
    Route::post('/older-adults', [OlderAdultController::class, 'store'])->can('create', OlderAdult::class);
    Route::get('/older-adults/{olderAdult}', [OlderAdultController::class, 'show'])->can('view', 'olderAdult');
    Route::put('/older-adults/{olderAdult}', [OlderAdultController::class, 'update'])->can('update', 'olderAdult');
    Route::delete('/older-adults/{olderAdult}', [OlderAdultController::class, 'destroy'])->can('delete', 'olderAdult');
    Route::get('/mobility-exercises', [MobilityExerciseController::class, 'index'])->can('viewAny', MobilityExercise::class);
    Route::post('/mobility-exercises', [MobilityExerciseController::class, 'store'])->can('create', MobilityExercise::class);
    Route::get('/mobility-exercises/{mobilityExercise}', [MobilityExerciseController::class, 'show'])->can('view', 'mobilityExercise');
    Route::put('/mobility-exercises/{mobilityExercise}', [MobilityExerciseController::class, 'update'])->can('update', 'mobilityExercise');
    Route::delete('/mobility-exercises/{mobilityExercise}', [MobilityExerciseController::class, 'destroy'])->can('delete', 'mobilityExercise');
});
