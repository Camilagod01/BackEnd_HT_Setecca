<?php

namespace App\Services\Statements;


use App\Models\Employee;
use App\Models\LoanPayment;
//use App\Services\Metrics\HoursMetricsService;
use App\Services\HoursCalculatorService;
use App\Services\Payroll\PayrollCalculator;
use Carbon\Carbon;

class StatementService
{
   public function __construct(
    protected HoursCalculatorService $hoursCalculator,
    protected PayrollCalculator      $payrollCalculator,
) {}


    /**
     * NUEVO: método compatible con el controlador API actual.
     *
     * Devuelve el formato "viejo" que usa Api\StatementController:
     * - employee
     * - period
     * - hours
     * - incomes
     * - deductions
     * - total_gross
     * - total_deductions
     * - net
     * - currency
     * - exchange_rate
     */
    public function build(int $employeeId, ?string $fromDate, ?string $toDate): array
    {
        // Normalizamos fechas (si vienen nulas, podrías poner defaults)
        $from = $fromDate ? Carbon::parse($fromDate)->startOfDay() : now()->startOfMonth();
        $to   = $toDate   ? Carbon::parse($toDate)->endOfDay()     : now()->endOfMonth();

        // Reutilizamos el método nuevo que ya arma todo
        $statement = $this->buildEmployeeStatement($employeeId, $from->toDateString(), $to->toDateString());

        $employee = $statement['employee'];
        $hoursRaw = $statement['hours'];
        $earnings = $statement['earnings'];
        $deducs   = $statement['deductions'];
        $summary  = $statement['summary'];

        // Armamos el bloque de horas en el estilo que ya usaba tu export
        $hours = [
    // Horas por tramo
    'regular_1x'   => $hoursRaw['regular_1x']   ?? ($hoursRaw['hours_1x']      ?? 0.0),
    'overtime_15'  => $hoursRaw['overtime_15']  ?? ($hoursRaw['hours_1_5x']    ?? 0.0),
    'double_20'    => $hoursRaw['double_20']    ?? ($hoursRaw['hours_2x']      ?? 0.0),

    // Total de horas trabajadas (según marcaciones)
    'total'        => $hoursRaw['total']        ?? 0.0,

    // ✅ Horas pagadas totales (1x + 1.5x + 2x)
    'paid_hours'   => $hoursRaw['paid_hours']
        ?? (
            ($hoursRaw['hours_1x']   ?? 0.0) +
            ($hoursRaw['hours_1_5x'] ?? 0.0) +
            ($hoursRaw['hours_2x']   ?? 0.0)
        ),

    // Para compatibilidad con lo que ya mostrabas en la UI
    'extra_day'    => $hoursRaw['extra_day']    ?? ($hoursRaw['hours_1_5x'] ?? 0.0),
    'extra_week'   => $hoursRaw['extra_week']   ?? ($hoursRaw['hours_2x']   ?? 0.0),

    // Incapacidades
    'sick_50_days' => $hoursRaw['sick_days_50'] ?? 0.0,
    'sick_0_days'  => $hoursRaw['sick_days_0']  ?? 0.0,
];


        // Ingresos: a partir de los items del PayrollCalculator
        $incomes = [];
        foreach ($earnings['items'] as $item) {
            $incomes[] = [
                'label'  => $item['label'],
                'amount' => $item['amount'],
            ];
        }

        // Deducciones: a partir de los items de deducciones
        $deductions = [];
        foreach ($deducs['items'] as $d) {
            $deductions[] = [
                'label'  => $d['label'],
                'amount' => $d['amount'],
            ];
        }

        $currency     = $summary['currency']  ?? ($earnings['currency'] ?? 'CRC');
        $exchangeRate = 1; // TODO: integrar con tu módulo de tipo de cambio real si quieres


        // Datos salariales derivados del summary (PayrollCalculator)
        $salaryType     = $summary['salary_type']        ?? 'monthly';
        $salarySource   = $summary['salary_source']      ?? null;
        $monthlyEst     = (float)($summary['monthly_salary_est'] ?? 0);
        $salaryCurrency = $summary['currency']           ?? $currency;

        return [
            'employee' => [
                'id'       => $employee['id'],
                'code'     => $employee['code'],
                'name'     => $employee['full_name'],
                'position' => $employee['position'] ?? null,

                // Datos salariales completos para el front
                'salary_type'        => $salaryType,
                'salary_source'      => $salarySource,
                'monthly_salary_est' => $monthlyEst,

                // Compatibilidad con el tipo Statement del front
                'salary_amount'   => $monthlyEst,
                'salary_currency' => $salaryCurrency,
            ],
            'period' => [
                'from' => $statement['range']['from'],
                'to'   => $statement['range']['to'],
            ],
            'hours' => $hours,
            'incomes' => $incomes,
            'deductions' => $deductions,
            'total_gross'       => $summary['gross'],
            'total_deductions'  => $summary['deductions'],
            'net'               => $summary['net'],
            'currency'          => $currency,
            'exchange_rate'     => $exchangeRate,
        ];

       

    }

    /**
     * Método "nuevo" que usamos internamente y también desde otros endpoints
     * para obtener un estado de cuenta estructurado.
     */
    public function buildEmployeeStatement(int $employeeId, string $fromDate, string $toDate): array
    {
        $employee = Employee::findOrFail($employeeId);

        $from = Carbon::parse($fromDate)->startOfDay();
        $to   = Carbon::parse($toDate)->endOfDay();

        // 1. Métricas de horas
       // $hours = $this->hoursMetrics->getForEmployeeAndRange($employee->id, $from, $to);
        $hours = $this->hoursCalculator->calcForEmployee(
    $employee->id,
    $from->toDateString(),
    $to->toDateString()
);



        // 2. Cálculo de ingresos
        $payroll = $this->payrollCalculator->calculateForPeriod($employee, $from, $to, $hours);

        // 3. Deducciones de préstamos (join con loans)
        $loanDeductions = LoanPayment::query()
            ->join('loans', 'loans.id', '=', 'loan_payments.loan_id')
            ->where('loans.employee_id', $employee->id)
            ->whereBetween('loan_payments.due_date', [
                $from->toDateString(),
                $to->toDateString(),
            ])
            ->where('loan_payments.status', 'pending')
            ->select('loan_payments.*', 'loans.currency')
            ->get()
            ->map(function (LoanPayment $p) {
                return [
                    'code'     => 'LOAN',
                    'label'    => 'Préstamo #' . $p->loan_id,
                    'amount'   => round($p->amount, 2),
                    'type'     => 'deduction',
                    'currency' => $p->currency ?? 'CRC',
                ];
            })
            ->values()
            ->all();

        $totalDeductions = collect($loanDeductions)->sum('amount');

        $gross = $payroll['gross'];
        $net   = $gross - $totalDeductions;

        $currency = $payroll['currency'] ?? 'CRC';

        return [
            'employee' => [
                'id'        => $employee->id,
                'code'      => $employee->code,
                'full_name' => $employee->full_name,
                'position'  => optional($employee->position)->name,
            ],
            'range' => [
                'from' => $from->toDateString(),
                'to'   => $to->toDateString(),
            ],
            'hours' => $hours,
            'earnings' => [
                'items'    => $payroll['items'],
                'gross'    => $gross,
                'currency' => $currency,
            ],
            'deductions' => [
                'items'    => $loanDeductions,
                'total'    => $totalDeductions,
                'currency' => $currency,
            ],
            'summary' => [
                'gross'       => $gross,
                'deductions'  => $totalDeductions,
                'net'         => round($net, 2),
                'currency'    => $currency,
                'salary_type'   => $payroll['salary_type'] ?? null,
                'salary_source' => $payroll['salary_source'] ?? null,
                'monthly_salary_est' => $payroll['monthly_salary_est'] ?? null,
            ],
        ];
    }
}
