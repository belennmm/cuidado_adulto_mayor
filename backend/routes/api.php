<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;

Route::get('/ping', function () {
    return response()->json(['ok' => true]);
});

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/users', [AdminUserController::class, 'index']);

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::patch('/users/{user}/approve', [AdminUserController::class, 'approve']);
});
