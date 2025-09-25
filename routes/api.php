<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\TimeEntryExportController;

//  Público
Route::post('/login', [AuthController::class, 'login']);

//  Requieren token
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Empleados
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employees/{id}', [EmployeeController::class, 'show']);
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::put('/employees/{id}', [EmployeeController::class, 'update']);
    Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);

    // Marcaciones
    Route::get('/time-entries', [TimeEntryController::class, 'index']);
    Route::post('/time-entries', [TimeEntryController::class, 'store']);

    // Métricas
Route::get('/metrics/hours', [\App\Http\Controllers\MetricsController::class, 'hours']);

// Marcaciones: actualización con auditoría
Route::patch('/time-entries/{id}', [\App\Http\Controllers\TimeEntryController::class, 'update']);

// (Opcional) Si prefieres parches en empleados:
Route::patch('/employees/{id}', [\App\Http\Controllers\EmployeeController::class, 'update']);

// Exportar marcaciones
  
Route::get('/exports/time-entries', [TimeEntryExportController::class, 'global']);            // vista global
Route::get('/employees/{id}/time-entries/export', [TimeEntryExportController::class, 'byEmployeeId'])
     ->name('employees.time-entries.export'); // por empleado
});




Route::apiResource('employees', EmployeeController::class)->only(['index','show','store','update']);
