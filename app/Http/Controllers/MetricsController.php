<?php
/**namespace App\Http\Controllers;

use App\Models\TimeEntry;
use Illuminate\Http\Request;


class MetricsController extends Controller
{
    public function hours(Request $req)
    {
        $data = $req->validate([
            'employee_id' => 'required|exists:employees,id',
            'from'        => 'required|date',
            'to'          => 'required|date|after_or_equal:from',
        ]);

        $rows = TimeEntry::where('employee_id', $data['employee_id'])
            ->whereDate('in_at', '>=', $data['from'])
            ->whereDate('in_at', '<=', $data['to'])
            ->get(['hours_worked']);

        $total = $rows->sum('hours_worked');

        return response()->json([
            'employee_id' => (int)$data['employee_id'],
            'from'        => $data['from'],
            'to'          => $data['to'],
            'total_hours' => round($total, 2),
        ]);
    }
}
**/








namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Employee;

class MetricsController extends Controller
{
    public function hours(Request $req)
    {
        $data = $req->validate([
            'from'        => ['required','date'],
            'to'          => ['required','date','after_or_equal:from'],
            'employee_id' => ['nullable','integer','exists:employees,id'],
            'group_by'    => ['nullable','in:employee'],
        ]);

        // Normaliza a YYYY-MM-DD para evitar problemas con ISO / zonas horarias
        $from = \Carbon\Carbon::parse($data['from'])->toDateString();
        $to   = \Carbon\Carbon::parse($data['to'])->toDateString();

        $svc = new HoursCalculatorService();

        // 1) Por empleado específico
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
                    // adjunta nombre para el front
                    $m['employee'] = [
                        'id' => $e->id,
                        'name' => trim($e->first_name.' '.$e->last_name),
                    ];
                    return $m;
                });

            return response()->json([
                'period' => ['from' => $from, 'to' => $to],
                'rows'   => $rows->values(),
                'totals' => [
                    'total'       => round($rows->sum('total'), 2),
                    'extra_day'   => round($rows->sum('extra_day'), 2),
                    'extra_week'  => round($rows->sum('extra_week'), 2),
                ],
            ]);
        }

        throw ValidationException::withMessages([
            'employee_id' => 'Debes pasar employee_id o group_by=employee.',
        ]);
    }
}