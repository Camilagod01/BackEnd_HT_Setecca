<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Employee;
use App\Services\HoursCalculatorService;   // <- nuestro servicio en app/Services
use Carbon\Carbon;

class MetricsController extends Controller
{
    /**
     * GET /api/metrics/hours
     * Admite:
     *  - employee_id + from + to  -> métricas de un empleado
     *  - from + to + group_by=employee -> resumen por empleado
     */
    public function hours(Request $req)
    {
        $data = $req->validate([
            'from'        => ['required','date'],
            'to'          => ['required','date','after_or_equal:from'],
            'employee_id' => ['nullable','integer','exists:employees,id'],
            'group_by'    => ['nullable','in:employee'],
        ]);

        // Normaliza a YYYY-MM-DD
        $from = Carbon::parse($data['from'])->toDateString();
        $to   = Carbon::parse($data['to'])->toDateString();

        $svc = app(HoursCalculatorService::class);

        // 1) Un solo empleado
        if (!empty($data['employee_id'])) {
            $res = $svc->calcForEmployee((int)$data['employee_id'], $from, $to);
            return response()->json($res);
        }

        // 2) Agrupado por empleado
        if (($data['group_by'] ?? null) === 'employee') {
            $rows = Employee::query()
                ->select('id','first_name','last_name')
                ->get()
                ->map(function (Employee $e) use ($svc, $from, $to) {
                    $m = $svc->calcForEmployee($e->id, $from, $to);
                    $m['employee'] = [
                        'id'   => $e->id,
                        'name' => trim(($e->first_name ?? '').' '.($e->last_name ?? '')),
                    ];
                    return $m;
                });

            return response()->json([
                'period' => ['from' => $from, 'to' => $to],
                'rows'   => $rows->values(),
                'totals' => [
                    'total'      => round($rows->sum('total'), 2),
                    'extra_day'  => round($rows->sum('extra_day'), 2),
                    'extra_week' => round($rows->sum('extra_week'), 2),
                ],
            ]);
        }

        throw ValidationException::withMessages([
            'employee_id' => 'Debes pasar employee_id o group_by=employee.',
        ]);
    }
}
