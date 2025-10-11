<?php

namespace App\Services;

use App\Models\Employee;
use App\Services\HoursCalculatorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AttendanceReportService
{
    public function __construct(
        private readonly HoursCalculatorService $hoursCalc
    ) {}

    /**
     * Genera reporte resumido por empleado.
     * @param string $from Fecha inicio (YYYY-MM-DD)
     * @param string $to   Fecha fin
     * @param array  $filters (puede incluir search o employee_id)
     */
    public function byEmployee(string $from, string $to, array $filters = []): array
    {
        $q = Employee::query()
            ->with('position:id,name')
            ->where('status', '!=', 'inactive');

        if (!empty($filters['employee_id'])) {
            $q->where('id', $filters['employee_id']);
        }

        if (!empty($filters['search'])) {
            $s = trim($filters['search']);
            $q->where(function($qq) use ($s) {
                $qq->where('first_name', 'like', "%{$s}%")
                   ->orWhere('last_name', 'like', "%{$s}%")
                   ->orWhere('code', 'like', "%{$s}%");
            });
        }

        $employees = $q->get(['id','code','first_name','last_name','position_id']);

        $rows = [];

        foreach ($employees as $emp) {
            try {
                $metrics = $this->hoursCalc->calcForEmployee($emp->id, $from, $to);

                $regular = max(0, ($metrics['total'] ?? 0)
                                  - ($metrics['extra_day'] ?? 0)
                                  - ($metrics['extra_week'] ?? 0));

                $rows[] = [
                    'employee_id'     => $emp->id,
                    'code'            => $emp->code,
                    'name'            => "{$emp->first_name} {$emp->last_name}",
                    'position'        => $emp->position->name ?? '',
                    'regular_hours'   => round($regular, 2),
                    'overtime_15'     => round($metrics['extra_day'] ?? 0, 2),
                    'overtime_20'     => round($metrics['extra_week'] ?? 0, 2),
                    'sick_50pct_days' => $metrics['sick_50pct_days'] ?? 0,
                    'sick_0pct_days'  => $metrics['sick_0pct_days'] ?? 0,
                    'attendance_days' => count($metrics['days'] ?? []),
                    'total'           => round($metrics['total'] ?? 0, 2),
                    'extra_day'       => round($metrics['extra_day'] ?? 0, 2),
                    'extra_week'      => round($metrics['extra_week'] ?? 0, 2),
                ];
            } catch (\Throwable $e) {
                Log::error("AttendanceReportService error for emp {$emp->id}: ".$e->getMessage());
            }
        }

        return $rows;
    }
}
