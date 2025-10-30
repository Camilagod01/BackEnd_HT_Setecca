<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;

class StatementService
{
    public function __construct(
        private readonly HoursCalculatorService $hoursCalc,
        private readonly EmployeeCompService $comp
    ) {}

    /**
     * Genera el estado de cuenta del empleado entre dos fechas (ambas YYYY-MM-DD).
     * Retorna montos en la MONEDA DEL EMPLEADO (CRC o USD).
     */
    public function generate(int $employeeId, string $from, string $to): array
    {
        $employee = Employee::findOrFail($employeeId);

        // 1) Salario efectivo: tipo, monto y moneda (de tu EmployeeCompService)
        $eff            = $this->comp->effectiveComp($employee);
        $salaryType     = $eff['salary_type'];             // 'monthly'|'hourly'
        $salaryAmount   = (float) $eff['salary_amount'];   // monto en salary_currency
        $salaryCurrency = $eff['salary_currency'];         // 'CRC'|'USD'

        // 2) Parámetros base y tipo de cambio (CRC por USD)
        $ps           = DB::table('payroll_settings')->where('id', 1)->first();
        $workdayHours = (float) ($ps->workday_hours ?? 8.0);

        $fxRow     = DB::table('exchange_rates')->orderByDesc('rate_date')->first();
        $crcPerUsd = (float) ($fxRow->rate ?? 0); // ej. 530.xx

        // 3) Horas del período (de tu HoursCalculatorService NUEVO)
        $m = $this->hoursCalc->calcForEmployee($employeeId, $from, $to);

        $hoursRegular1x = (float) ($m['total'] ?? 0);  // total trabajado
        // OJO: el servicio ya separa en buckets finales:
        $hours15xFinal  = (float) ($m['extra_day']  ?? 0); // 1.5x final
        $hours20xFinal  = (float) ($m['extra_week'] ?? 0); // 2.0x final
        // Para obtener horas regulares puras: total - 1.5x - 2.0x
        $hours1xFinal   = max(0.0, $hoursRegular1x - $hours15xFinal - $hours20xFinal);

        $sick50Days     = (int) ($m['sick_50pct_days'] ?? 0);
        $sick0Days      = (int) ($m['sick_0pct_days']  ?? 0);

        // 4) Tarifa por hora en CRC
        $hourlyCRC = $this->hourlyRateCRC($salaryType, $salaryAmount, $salaryCurrency, $crcPerUsd, $workdayHours);

        // 5) Ingresos (en CRC) — usando los buckets finales del HoursCalculatorService
        $income1x  = $hourlyCRC * $hours1xFinal;           // incluye feriados NO trabajados (1x) porque el service ya los pone en regToday
        $income15  = $hourlyCRC * 1.5 * $hours15xFinal;    // extra diario
        $income20  = $hourlyCRC * 2.0 * $hours20xFinal;    // domingos/feriados trabajados + exceso semanal

        // Si luego agregas “Ajustes/Aguinaldo”, súmalos aquí:
        $incomeAdjust = 0.0;

        // 6) Deducciones (en CRC)
        $dedSick  = $this->deductionSickCRC($salaryType, $salaryAmount, $salaryCurrency, $crcPerUsd, $workdayHours, $sick50Days, $sick0Days);
        //  ADELANTOS
        $dedAdv = (float) DB::table('advances')
                        ->where('employee_id', $employeeId)
                        ->whereBetween('granted_at', [$from, $to])
                        ->sum('amount');

        //  PRÉSTAMOS (cuotas pagadas)
        $dedLoans = (float) DB::table('loan_payments')
                        ->whereBetween('due_date', [$from, $to])
                        ->sum('amount');

        //  EMBARGOS
        $dedGarn = (float) DB::table('garnishments')
                        ->where('employee_id', $employeeId)
                        ->where(function ($q) use ($from, $to) {
                            $q->whereBetween('start_date', [$from, $to])
                            ->orWhereBetween('end_date', [$from, $to]);
                        })
                        ->sum('value');

        // 7) Totales en CRC
        $incomesCRC = [
            ['label' => 'Horas 1x (incluye feriados no trabajados)', 'amount' => round($income1x, 2)],
            ['label' => 'Horas extra 1.5x',                          'amount' => round($income15, 2)],
            ['label' => 'Horas doble 2x',                            'amount' => round($income20, 2)],
            ['label' => 'Ajustes/Aguinaldo',                         'amount' => round($incomeAdjust, 2)],
        ];
        $dedCRC = [
            ['label' => 'Incapacidades', 'amount' => round($dedSick, 2)],
            ['label' => 'Adelantos',     'amount' => round($dedAdv, 2)],
            ['label' => 'Préstamos',     'amount' => round($dedLoans, 2)],
            ['label' => 'Embargos',      'amount' => round($dedGarn, 2)],
        ];

        $grossCRC    = array_sum(array_column($incomesCRC, 'amount'));
        $totalDedCRC = array_sum(array_column($dedCRC, 'amount'));
        $netCRC      = $grossCRC - $totalDedCRC;

        // 8) Convertimos a la moneda del empleado para presentar
        $toEmp = fn(float $v) => $this->toEmployeeCurrency($v, $salaryCurrency, $crcPerUsd);

        $incomes    = array_map(fn($r) => ['label'=>$r['label'],'amount'=>round($toEmp($r['amount']),2)], $incomesCRC);
        $deductions = array_map(fn($r) => ['label'=>$r['label'],'amount'=>round($toEmp($r['amount']),2)], $dedCRC);

        return [
            'employee' => [
                'id'               => $employee->id,
                'code'             => $employee->code,
                'name'             => "{$employee->first_name} {$employee->last_name}",
                'salary_type'      => $salaryType,
                'salary_amount'    => $salaryAmount,
                'salary_currency'  => $salaryCurrency,
            ],
            'period'   => ['from'=>$from,'to'=>$to],
            'hours'    => [
                'regular_1x'  => round($hours1xFinal, 2),
                'overtime_15' => round($hours15xFinal, 2),
                'double_20'   => round($hours20xFinal, 2),
                'sick_50pct_days' => $sick50Days,
                'sick_0pct_days'  => $sick0Days,
            ],
            'incomes'          => $incomes,
            'deductions'       => $deductions,
            'total_gross'      => round($toEmp($grossCRC), 2),
            'total_deductions' => round($toEmp($totalDedCRC), 2),
            'net'              => round($toEmp($netCRC), 2),
            'currency'         => $salaryCurrency,   // CRC o USD
            'exchange_rate'    => $crcPerUsd,        // CRC por 1 USD (referencia)
        ];
    }

    // ---------- Helpers ----------

    private function hourlyRateCRC(string $salaryType, float $amount, string $currency, float $crcPerUsd, float $workdayHours): float
    {
        $amountCRC = ($currency === 'USD' && $crcPerUsd > 0) ? $amount * $crcPerUsd : $amount;

        if ($salaryType === 'hourly') {
            return $amountCRC;
        }
        // monthly → estimar tarifa por hora = mensual / (30 * workdayHours)
        $hoursMonth = 30.0 * max(1.0, $workdayHours);
        return $hoursMonth > 0 ? $amountCRC / $hoursMonth : 0.0;
    }

    private function deductionSickCRC(string $salaryType, float $amount, string $currency, float $crcPerUsd, float $workdayHours, int $days50, int $days0): float
    {
        $amountCRC = ($currency === 'USD' && $crcPerUsd > 0) ? $amount * $crcPerUsd : $amount;

        $dailyCRC = 0.0;
        if ($salaryType === 'monthly') {
            $dailyCRC = $amountCRC / 30.0;
        } else { // hourly
            $dailyCRC = $amountCRC * max(1.0, $workdayHours); // horas por día
        }

        // 50% y 0% (según tu definición de HoursCalculatorService)
        return ($days50 * $dailyCRC * 0.5) + ($days0 * $dailyCRC * 1.0);
    }

    private function toEmployeeCurrency(float $vCRC, string $empCurrency, float $crcPerUsd): float
    {
        if ($empCurrency === 'USD' && $crcPerUsd > 0) {
            return $vCRC / $crcPerUsd;
        }
        return $vCRC;
    }

    public function build(int $employeeId, ?string $from, ?string $to): array
    {
        return $this->generate($employeeId, $from ?? now()->startOfMonth()->toDateString(), $to ?? now()->endOfMonth()->toDateString());
    }
}