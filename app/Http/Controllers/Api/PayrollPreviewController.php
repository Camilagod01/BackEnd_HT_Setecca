<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Payroll\PayrollService;
use Illuminate\Support\Carbon;

class PayrollPreviewController extends Controller
{
    public function __invoke(Request $request, PayrollService $payroll)
    {
        // 1) Validación: devuelve 422 con errores detallados si algo falla
        $data = $request->validate([
            'employee_id' => ['required','integer','exists:employees,id'],
            'from'        => ['required','date'],
            'to'          => ['required','date','after_or_equal:from'],
        ]);

        // 2) Normalización de tipos/fechas (YYYY-MM-DD)
        $employeeId = (int) $data['employee_id'];
        $from = Carbon::parse($data['from'])->toDateString();
        $to   = Carbon::parse($data['to'])->toDateString();

        // 3) Llamada segura al servicio (con manejo de errores controlado)
        try {
            $result = $payroll->previewEmployee($employeeId, $from, $to);
            return response()->json($result);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'ok'      => false,
                'message' => 'No se pudo generar el preview',
                // En local puedes exponer el mensaje, en prod déjalo en null
                'error'   => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
