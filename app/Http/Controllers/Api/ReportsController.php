<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportsController extends Controller
{
    /**
     * GET /api/reports/summary?from=YYYY-MM-DD&to=YYYY-MM-DD&employee_id=
     *
     * Devuelve un resumen por empleado del periodo indicado:
     * - avances: monto total por status (pending/applied)
     * - préstamos: cantidad y principal estimado en el periodo (por start_date)
     * - cuotas de préstamo: pagadas/pending/omitted (por due_date)
     * - incapacidades: días que solapan en el rango
     * - vacaciones: días que solapan en el rango
     * - ausencias: horas totales (kind=hours) y días solapados (kind=days)
     * - justificaciones: conteo por estado en el rango
     */
    public function summary(Request $request)
    {
        $from = $request->query('from');
        $to   = $request->query('to');
        $emp  = $request->query('employee_id');

        // Defaults: mes actual
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
        $toDate   = $to   ? Carbon::parse($to)->endOfDay()     : now()->endOfMonth();

        // Obtiene empleados involucrados en el periodo (o uno específico)
        $employeesQuery = DB::table('employees')
            ->select('id', 'code', 'first_name', 'last_name');

        if ($emp) {
            $employeesQuery->where('id', $emp);
        }

        $employees = $employeesQuery->get();

        // Prepara índice del resultado
        $byEmp = [];
        foreach ($employees as $e) {
            $byEmp[$e->id] = [
                'employee' => [
                    'id' => $e->id,
                    'code' => $e->code,
                    'full_name' => trim(($e->first_name ?? '') . ' ' . ($e->last_name ?? '')),
                ],
                'advances' => ['pending_amount' => 0.0, 'applied_amount' => 0.0, 'count' => 0],
                'loans'    => ['created_count' => 0, 'principal_sum' => 0.0],
                'loan_payments' => ['paid' => 0, 'pending' => 0, 'skipped' => 0],
                'sick_leaves_days' => 0,   // días solapados
                'vacations_days'   => 0,   // días solapados
                'absences' => ['hours' => 0.0, 'days' => 0],
                'justifications' => ['pending' => 0, 'approved' => 0, 'rejected' => 0],
            ];
        }

        // --------- ADVANCES (granted_at dentro del rango) ----------
        $adv = DB::table('advances')
            ->select('employee_id', 'status', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as cnt'))
            ->whereBetween('granted_at', [$fromDate->toDateString(), $toDate->toDateString()])
            ->when($emp, fn($q) => $q->where('employee_id', $emp))
            ->groupBy('employee_id', 'status')
            ->get();
        foreach ($adv as $r) {
            if (!isset($byEmp[$r->employee_id])) continue;
            $byEmp[$r->employee_id]['advances']['count'] += (int)$r->cnt;
            if ($r->status === 'pending')  $byEmp[$r->employee_id]['advances']['pending_amount'] += (float)$r->total;
            if ($r->status === 'applied')  $byEmp[$r->employee_id]['advances']['applied_amount'] += (float)$r->total;
        }

        // --------- LOANS (creados en el rango por start_date) ----------
        if (DB::getSchemaBuilder()->hasTable('loans')) {
            $loans = DB::table('loans')
                ->select('employee_id', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(COALESCE(principal, 0)) as principal_sum'))
                ->whereBetween('start_date', [$fromDate->toDateString(), $toDate->toDateString()])
                ->when($emp, fn($q) => $q->where('employee_id', $emp))
                ->groupBy('employee_id')
                ->get();
            foreach ($loans as $r) {
                if (!isset($byEmp[$r->employee_id])) continue;
                $byEmp[$r->employee_id]['loans']['created_count'] += (int)$r->cnt;
                $byEmp[$r->employee_id]['loans']['principal_sum'] += (float)$r->principal_sum;
            }
        }

        // --------- LOAN PAYMENTS (cuotas por due_date) ----------
        if (DB::getSchemaBuilder()->hasTable('loan_payments')) {
            $lp = DB::table('loan_payments as p')
                ->join('loans as l', 'l.id', '=', 'p.loan_id')
                ->select('l.employee_id', 'p.status', DB::raw('COUNT(*) as cnt'))
                ->whereBetween('p.due_date', [$fromDate->toDateString(), $toDate->toDateString()])
                ->when($emp, fn($q) => $q->where('l.employee_id', $emp))
                ->groupBy('l.employee_id', 'p.status')
                ->get();
            foreach ($lp as $r) {
                if (!isset($byEmp[$r->employee_id])) continue;
                if ($r->status === 'paid')    $byEmp[$r->employee_id]['loan_payments']['paid'] += (int)$r->cnt;
                if ($r->status === 'pending') $byEmp[$r->employee_id]['loan_payments']['pending'] += (int)$r->cnt;
                if ($r->status === 'skipped') $byEmp[$r->employee_id]['loan_payments']['skipped'] += (int)$r->cnt;
            }
        }

        // Helpers de solape de días
        $overlapDays = function ($start, $end) use ($fromDate, $toDate) {
            $s = Carbon::parse($start)->startOfDay();
            $e = Carbon::parse($end)->endOfDay();
            $startMax = $s->greaterThan($fromDate) ? $s : $fromDate;
            $endMin   = $e->lessThan($toDate) ? $e : $toDate;
            if ($endMin->lt($startMax)) return 0;
            return $startMax->diffInDays($endMin) + 1; // inclusivo
        };

        // --------- SICK LEAVES (solapadas con el rango) ----------
        if (DB::getSchemaBuilder()->hasTable('sick_leaves')) {
            $sl = DB::table('sick_leaves')
                ->select('id', 'employee_id', 'start_date', 'end_date')
                ->whereDate('start_date', '<=', $toDate->toDateString())
                ->whereDate('end_date',   '>=', $fromDate->toDateString())
                ->when($emp, fn($q) => $q->where('employee_id', $emp))
                ->get();
            foreach ($sl as $r) {
                if (!isset($byEmp[$r->employee_id])) continue;
                $byEmp[$r->employee_id]['sick_leaves_days'] += $overlapDays($r->start_date, $r->end_date);
            }
        }

        // --------- VACATIONS (solapadas con el rango) ----------
        if (DB::getSchemaBuilder()->hasTable('vacations')) {
            $vc = DB::table('vacations')
                ->select('id', 'employee_id', 'start_date', 'end_date')
                ->whereDate('start_date', '<=', $toDate->toDateString())
                ->whereDate('end_date',   '>=', $fromDate->toDateString())
                ->when($emp, fn($q) => $q->where('employee_id', $emp))
                ->get();
            foreach ($vc as $r) {
                if (!isset($byEmp[$r->employee_id])) continue;
                $byEmp[$r->employee_id]['vacations_days'] += $overlapDays($r->start_date, $r->end_date);
            }
        }

        // --------- ABSENCES (horas y días) ----------
        if (DB::getSchemaBuilder()->hasTable('absences')) {
            // Horas (kind=hours) por fecha dentro del rango
            $abHours = DB::table('absences')
                ->select('employee_id', DB::raw('SUM(COALESCE(hours,0)) as hrs'))
                ->where('kind', 'hours')
                ->whereBetween('start_date', [$fromDate->toDateString(), $toDate->toDateString()])
                ->when($emp, fn($q) => $q->where('employee_id', $emp))
                ->groupBy('employee_id')
                ->get();
            foreach ($abHours as $r) {
                if (!isset($byEmp[$r->employee_id])) continue;
                $byEmp[$r->employee_id]['absences']['hours'] += (float)$r->hrs;
            }

            // Días (kind=days) solapados
            $abDays = DB::table('absences')
                ->select('id', 'employee_id', 'start_date', 'end_date')
                ->where('kind', 'days')
                ->whereDate('start_date', '<=', $toDate->toDateString())
                ->whereDate('end_date',   '>=', $fromDate->toDateString())
                ->when($emp, fn($q) => $q->where('employee_id', $emp))
                ->get();
            foreach ($abDays as $r) {
                if (!isset($byEmp[$r->employee_id])) continue;
                $byEmp[$r->employee_id]['absences']['days'] += $overlapDays($r->start_date, $r->end_date);
            }
        }

        // --------- JUSTIFICATIONS (por fecha) ----------
        if (DB::getSchemaBuilder()->hasTable('justifications')) {
            $js = DB::table('justifications')
                ->select('employee_id', 'status', DB::raw('COUNT(*) as cnt'))
                ->whereBetween('date', [$fromDate->toDateString(), $toDate->toDateString()])
                ->when($emp, fn($q) => $q->where('employee_id', $emp))
                ->groupBy('employee_id', 'status')
                ->get();
            foreach ($js as $r) {
                if (!isset($byEmp[$r->employee_id])) continue;
                if (in_array($r->status, ['pending','approved','rejected'])) {
                    $byEmp[$r->employee_id]['justifications'][$r->status] += (int)$r->cnt;
                }
            }
        }

        // Devuelve solo empleados con datos o todos (mantengo todos para consistencia)
        return array_values($byEmp);
    }
}
