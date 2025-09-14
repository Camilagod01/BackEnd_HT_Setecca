<?php
namespace App\Http\Controllers;

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
