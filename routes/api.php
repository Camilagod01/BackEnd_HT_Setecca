<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\TimeEntryController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\TimeEntryExportController;
use App\Http\Controllers\Api\PositionController;

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
    Route::patch('/employees/{id}', [EmployeeController::class, 'update']); // opcional
    Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
    Route::patch('/employees/{employee}/position', [EmployeeController::class, 'updatePosition']);

    // Marcaciones
    Route::get('/time-entries', [TimeEntryController::class, 'index']);
    Route::post('/time-entries', [TimeEntryController::class, 'store']);
    Route::patch('/time-entries/{id}', [\App\Http\Controllers\Api\TimeEntryController::class, 'update']);

    // Métricas
    Route::get('/metrics/hours', [\App\Http\Controllers\Api\MetricsController::class, 'hours']);

    Route::patch('/employees/{id}', [\App\Http\Controllers\Api\EmployeeController::class, 'update']);

    // Exportar marcaciones

    Route::get('/exports/time-entries', [TimeEntryExportController::class, 'global']);
    Route::get('/employees/{id}/time-entries/export', [TimeEntryExportController::class, 'byEmployeeId'])
        ->name('employees.time-entries.export');

    // Puestos
    Route::get('/positions', [PositionController::class, 'index']);
    Route::get('/positions/{id}', [PositionController::class, 'show']);
    Route::post('/positions', [PositionController::class, 'store']);
    Route::patch('/positions/{id}', [PositionController::class, 'update']);
    Route::delete('/positions/{id}', [PositionController::class, 'destroy']);

    // Incapacidades
    Route::get('/sick-leaves', [\App\Http\Controllers\Api\SickLeaveController::class, 'index']);
    Route::post('/sick-leaves', [\App\Http\Controllers\Api\SickLeaveController::class, 'store']);
    Route::patch('/sick-leaves/{id}', [\App\Http\Controllers\Api\SickLeaveController::class, 'update']);
    Route::delete('/sick-leaves/{id}', [\App\Http\Controllers\Api\SickLeaveController::class, 'destroy']);

    // Reporte de asistencia
    Route::get('/reports/attendance', [\App\Http\Controllers\Api\Reports\AttendanceReportController::class, 'index']);
    Route::get('/reports/attendance/export', [\App\Http\Controllers\Api\Reports\AttendanceReportController::class, 'export']);

});
