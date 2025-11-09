<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\TimeEntryController;
use App\Http\Controllers\Api\MetricsController;
use App\Http\Controllers\Api\TimeEntryExportController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\AdvanceController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanPaymentController;
use App\Http\Controllers\Api\SickLeaveController;
use App\Http\Controllers\Api\VacationController;
use App\Models\Vacation;
use App\Http\Controllers\Api\AbsenceController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\PayrollSettingController;
use App\Http\Controllers\Api\JustificationController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\StatementController;
use App\Http\Controllers\Api\EmployeeImportController;
use App\Http\Controllers\Api\PayrollPreviewController;
use App\Http\Controllers\Api\GarnishmentController;
//use App\Http\Controllers\Api\EmployeeController;
//use App\Http\Controllers\Api\EmployeeController as ApiEmployeeController;


//  Público
Route::post('/login', [AuthController::class, 'login']);


// Actualizar una cuota específica
Route::patch('/loan-payments/{loanPayment}', [LoanPaymentController::class, 'update']);
//Opciones de empleados
//Route::get('/employees/options', [EmployeeController::class, 'options']);
Route::get('/employees/options', [EmployeeController::class, 'options'])->name('employees.options');



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

    //Opciones de empleados
    //Route::get('/employees/options', [EmployeeController::class, 'options']);
    Route::get('/employees/options', [EmployeeController::class, 'options'])->name('employees.options');



    // Adelantos
    Route::get('/advances', [AdvanceController::class, 'index']);
    Route::post('/advances', [AdvanceController::class, 'store']);
    Route::patch('/advances/{advance}', [AdvanceController::class, 'update']);
    Route::delete('/advances/{advance}', [AdvanceController::class, 'destroy']);

    // Prestamos
    Route::get('/loans', [LoanController::class, 'index']);
    Route::get('/loans/{loan}', [LoanController::class, 'show']);
    Route::post('/loans', [LoanController::class, 'store']);
    Route::patch('/loans/{loan}', [LoanController::class, 'update']);
    Route::delete('/loans/{loan}', [LoanController::class, 'destroy']);

    // Cuotas de un préstamo
    Route::get('/loans/{loan}/payments', [LoanController::class, 'payments']);

    // Incapacidades
    Route::get   ('/sick-leaves',              [SickLeaveController::class, 'index']);
    Route::post  ('/sick-leaves',              [SickLeaveController::class, 'store']);
    Route::get   ('/sick-leaves/{id}',         [SickLeaveController::class, 'show'])->whereNumber('id');
    Route::patch ('/sick-leaves/{id}',         [SickLeaveController::class, 'update'])->whereNumber('id');
    Route::delete('/sick-leaves/{id}',         [SickLeaveController::class, 'destroy'])->whereNumber('id');

    // Vacaciones
    Route::get   ('/vacations',                [VacationController::class, 'index']);
    Route::post  ('/vacations',                [VacationController::class, 'store']);
    Route::get   ('/vacations/{vacation}',     [VacationController::class, 'show'])->whereNumber('vacation');
    Route::patch ('/vacations/{vacation}',     [VacationController::class, 'update'])->whereNumber('vacation');
    Route::delete('/vacations/{vacation}',     [VacationController::class, 'destroy'])->whereNumber('vacation');


    // PruebaVacaciones
    Route::patch('/vacations/{id}/status', function(Request $r, $id) {
        $v = Vacation::findOrFail($id);
        $status = $r->input('status');
        if (!in_array($status, ['pending','approved','rejected'])) {
            return response()->json(['message' => 'Estado inválido'], 422);
        }
        $v->status = $status;
        $v->save();
        return $v->fresh();
    })->whereNumber('id');


    // Permisos
    Route::get   ('/absences',             [AbsenceController::class, 'index']);
    Route::post  ('/absences',             [AbsenceController::class, 'store']);
    Route::get   ('/absences/{absence}',   [AbsenceController::class, 'show'])->whereNumber('absence');
    Route::patch ('/absences/{absence}',   [AbsenceController::class, 'update'])->whereNumber('absence');
    Route::delete('/absences/{absence}',   [AbsenceController::class, 'destroy'])->whereNumber('absence');

    // Feriados
    Route::get   ('/holidays',            [HolidayController::class, 'index']);
    Route::post  ('/holidays',            [HolidayController::class, 'store']);
    Route::get   ('/holidays/{holiday}',  [HolidayController::class, 'show'])->whereNumber('holiday');
    Route::patch ('/holidays/{holiday}',  [HolidayController::class, 'update'])->whereNumber('holiday');
    Route::delete('/holidays/{holiday}',  [HolidayController::class, 'destroy'])->whereNumber('holiday');

    // Configuración de Planilla
    Route::get('/payroll-settings',  [PayrollSettingController::class, 'show']);
    Route::patch('/payroll-settings',[PayrollSettingController::class, 'update']);

    // Justificaciones
    Route::get   ('/justifications',                     [JustificationController::class, 'index']);
    Route::post  ('/justifications',                     [JustificationController::class, 'store']);
    Route::get   ('/justifications/{justification}',     [JustificationController::class, 'show'])->whereNumber('justification');
    Route::patch ('/justifications/{justification}',     [JustificationController::class, 'update'])->whereNumber('justification');
    Route::patch ('/justifications/{justification}/status', [JustificationController::class, 'updateStatus'])->whereNumber('justification');
    Route::delete('/justifications/{justification}',     [JustificationController::class, 'destroy'])->whereNumber('justification');

    // Reportes
    Route::get('/reports/summary', [ReportsController::class, 'summary']);

    // Estado de cuenta
    Route::get('/statements/{id}', [StatementController::class, 'show']);
    Route::get('statements/{id}/export', [StatementController::class, 'export']);
    Route::get('/statements/by-code/{code}', [StatementController::class, 'showByCode']);

    // Importe de empleados
    Route::post('/employees/import', [EmployeeImportController::class, 'import']);
    Route::get('/employees/import/template', [EmployeeImportController::class, 'template']);

    //Dobles feriados, horas extras
    Route::match(['GET', 'POST'], '/payroll/preview', PayrollPreviewController::class);

    //embargos
    //Route::get('/garnishments', [GarnishmentController::class, 'index']);
    Route::get('/garnishments/{id}', [\App\Http\Controllers\Api\GarnishmentController::class, 'show']);
    Route::post('/garnishments', [\App\Http\Controllers\Api\GarnishmentController::class, 'store']);
    Route::patch('/garnishments/{garnishment}', [\App\Http\Controllers\Api\GarnishmentController::class, 'update']);
    Route::delete('/garnishments/{garnishment}', [\App\Http\Controllers\Api\GarnishmentController::class, 'destroy']);
    Route::get('/garnishments', [GarnishmentController::class, 'index']);

    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employees/{id}', [EmployeeController::class, 'show']);