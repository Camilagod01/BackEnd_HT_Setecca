<?php

namespace App\Services\Payroll;

use App\Models\Employee;
//use App\Models\PayrollSetting;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

class PayrollCalculator
{
    public function calculateForPeriod(Employee $employee, Carbon $from, Carbon $to, array $hoursMetrics): array
    {
        // 1. Traer settings (fila única)
        // 1. Traer settings (fila única desde la tabla payroll_settings)
$settings = DB::table('payroll_settings')->first();

if (!$settings) {
    throw new \RuntimeException('No hay configuración de payroll_settings en la base de datos');
}

// Usamos workday_hours como cantidad de horas que tiene un día laboral
$workdayHours = (float) ($settings->workday_hours ?? 8.0);


        if ($workdayHours <= 0) {
            throw new \RuntimeException('workday_hours inválido en payroll_settings');
        }

        // Para convertir salario mensual a tarifa por hora necesitamos asumir días por mes
        // (no existe days_per_month en tu tabla, así que usamos 26 como estándar de planilla)
        $daysPerMonth = 26;
        $baseHoursPerMonth = $workdayHours * $daysPerMonth;

        if ($baseHoursPerMonth <= 0) {
            throw new \RuntimeException('Horas base por mes inválidas (workday_hours * días_por_mes)');
        }

        // 2. Determinar tipo de salario y fuente (puesto vs override)
        $salaryType = $employee->salary_type ?? 'monthly'; // 'monthly' o 'hourly'

        $hourRate      = 0.0;
        $monthlySalary = 0.0;
        $salarySource  = null;
        $currency      = null;

        if ($employee->use_position_salary) {
            // Usa salario del puesto
            $position = $employee->position; // asume relación position() en el modelo Employee

            if (!$position) {
                throw new \RuntimeException('Empleado sin puesto asignado para cálculo de salario');
            }

            // Tomamos moneda del puesto
            $currency = $position->default_salary_currency ?? $position->currency ?? 'CRC';

            if ($salaryType === 'hourly') {
                // Salario por hora desde el puesto
                $hourRate      = (float) ($position->base_hourly_rate ?? 0.0);
                $monthlySalary = $hourRate * $baseHoursPerMonth;
                $salarySource  = 'position_hourly';
            } else {
                // Salario mensual desde el puesto
                $monthlySalary = (float) ($position->default_salary_amount ?? 0.0);
                $hourRate      = $monthlySalary / $baseHoursPerMonth;
                $salarySource  = 'position_monthly';
            }
        } else {
            // Usa salario override en el empleado
            $override = (float) ($employee->salary_override_amount ?? 0.0);
            $currency = $employee->salary_override_currency ?? 'CRC';

            if ($salaryType === 'hourly') {
                $hourRate      = $override;
                $monthlySalary = $hourRate * $baseHoursPerMonth;
                $salarySource  = 'employee_hourly';
            } else {
                $monthlySalary = $override;
                $hourRate      = $monthlySalary / $baseHoursPerMonth;
                $salarySource  = 'employee_monthly';
            }
        }

        if ($hourRate <= 0) {
            throw new \RuntimeException(
                'No se pudo determinar una tarifa por hora válida para el empleado ID ' . $employee->id
            );
        }

        $dayRate = $hourRate * $workdayHours;

        // 3. Extraer métricas de horas (claves según tu /api/metrics/hours)
        $hours1x     = (float) ($hoursMetrics['hours_1x']     ?? 0.0);
        $hours15x    = (float) ($hoursMetrics['hours_1_5x']   ?? 0.0);
        $hours2x     = (float) ($hoursMetrics['hours_2x']     ?? 0.0);
        $sickDays50  = (float) ($hoursMetrics['sick_days_50'] ?? 0.0);
        $sickDays0   = (float) ($hoursMetrics['sick_days_0']  ?? 0.0); // solo informativo

        // 4. Cálculos de ingresos
        $amount1x     = $hours1x  * $hourRate;
        $amount15x    = $hours15x * $hourRate * 1.5;
        $amount2x     = $hours2x  * $hourRate * 2.0;
        $amountSick50 = $sickDays50 * $dayRate * 0.5;

        $items = [
            [
                'code'        => 'BASE_1X',
                'label'       => 'Horas 1x (incluye feriados no trabajados)',
                'hours'       => $hours1x,
                'multiplier'  => 1.0,
                'unit_amount' => round($hourRate, 2),
                'amount'      => round($amount1x, 2),
                'type'        => 'earning',
            ],
            [
                'code'        => 'OT_1_5X',
                'label'       => 'Horas extra 1.5x',
                'hours'       => $hours15x,
                'multiplier'  => 1.5,
                'unit_amount' => round($hourRate, 2),
                'amount'      => round($amount15x, 2),
                'type'        => 'earning',
            ],
            [
                'code'        => 'OT_2X',
                'label'       => 'Horas extra 2x',
                'hours'       => $hours2x,
                'multiplier'  => 2.0,
                'unit_amount' => round($hourRate, 2),
                'amount'      => round($amount2x, 2),
                'type'        => 'earning',
            ],
            [
                'code'        => 'SICK_50',
                'label'       => 'Incapacidades (50% pago)',
                'days'        => $sickDays50,
                'multiplier'  => 0.5,
                'unit_amount' => round($dayRate, 2),
                'amount'      => round($amountSick50, 2),
                'type'        => 'earning',
            ],
        ];

        // 5. Total de ingresos brutos
        $gross = 0.0;
        foreach ($items as $it) {
            $gross += $it['amount'];
        }

        return [
            'currency'      => $currency,
            'salary_type'   => $salaryType,
            'salary_source' => $salarySource,
            'hour_rate'     => round($hourRate, 4),
            'day_rate'      => round($dayRate, 4),
            'monthly_salary_est' => round($monthlySalary, 2),
            'items'         => $items,
            'gross'         => round($gross, 2),
            'meta'          => [
                'workday_hours'        => $workdayHours,
                'days_per_month_used'  => $daysPerMonth,
                'base_hours_per_month' => $baseHoursPerMonth,
                'sick_days_0'          => $sickDays0,
            ],
        ];
    }
}
