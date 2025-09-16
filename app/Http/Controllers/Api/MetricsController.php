<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\MetricsHoursRequest;
use App\Models\TimeEntry;

class MetricsController extends Controller
{
    public function hours(MetricsHoursRequest $req)
    {
        $data = $req->validated();

        $rows = TimeEntry::where('employee_id', $data['employee_id'])
            ->whereDate('in_at', '>=', $data['from'])
            ->whereDate('in_at', '<=', $data['to'])
            ->get(['hours_worked']);

        $total = $rows->sum('hours_worked');

        return [
            'employee_id' => (int) $data['employee_id'],
            'from'        => $data['from'],
            'to'          => $data['to'],
            'total_hours' => round($total, 2),
        ];
    }
}
