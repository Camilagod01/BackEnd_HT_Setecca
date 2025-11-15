<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\TimeEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EmployeeAttendanceController extends Controller
{
    /**
     * Devuelve asistencia simple para el módulo de Asistencia.
     */
    public function attendanceForEmployee(Request $request, Employee $employee)
    {
        $from = $request->query('from');
        $to   = $request->query('to');

        $fromDate = $from ? Carbon::parse($from)->startOfDay() : now()->subMonth()->startOfDay();
        $toDate   = $to   ? Carbon::parse($to)->endOfDay()   : now()->endOfDay();

        $entries = TimeEntry::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->orderBy('work_date')
            ->get()
            ->map(function (TimeEntry $entry) {

                // Calcula horas trabajadas si no está pregrabado
                $hours = null;
                if ($entry->check_in && $entry->check_out) {
                    $hours = Carbon::parse($entry->check_in)
                        ->diffInMinutes(Carbon::parse($entry->check_out)) / 60;
                }

                return [
                    'id'        => $entry->id,
                    'work_date' => $entry->work_date,
                    'check_in'  => $entry->check_in,
                    'check_out' => $entry->check_out,
                    'notes'     => $entry->notes,
                    'hours'     => $hours,
                    'source'    => $entry->source,
                ];
            });

        return response()->json([
            'ok'       => true,
            'employee' => [
                'id'   => $employee->id,
                'code' => $employee->code ?? null,
                'name' => $employee->full_name ?? ($employee->first_name . ' ' . $employee->last_name),
            ],
            'range' => [
                'from' => $fromDate->toDateString(),
                'to'   => $toDate->toDateString(),
            ],
            'entries' => $entries,
        ]);
    }
}
