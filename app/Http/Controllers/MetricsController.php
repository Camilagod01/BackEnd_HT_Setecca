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

use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    public function hours(Request $req)
    {
        $data = $req->validate([
            'employee_id' => 'required|exists:employees,id',
            'from'        => 'required|date',
            'to'          => 'required|date|after_or_equal:from',
        ]);

        // Calcular minutos trabajados con TIMESTAMPDIFF en MySQL
        $result = TimeEntry::where('employee_id', $data['employee_id'])
            ->whereBetween('work_date', [$data['from'], $data['to']])
            ->whereNotNull('check_in')
            ->whereNotNull('check_out')
            ->select(DB::raw('SUM(TIMESTAMPDIFF(MINUTE, check_in, check_out)) as minutes'))
            ->first();

        $minutes = (int) ($result->minutes ?? 0);
        $hours   = round($minutes / 60, 2);

        return response()->json([
            'employee_id' => (int) $data['employee_id'],
            'from'        => $data['from'],
            'to'          => $data['to'],
            'total_hours' => $hours,
        ]);
    }
}
