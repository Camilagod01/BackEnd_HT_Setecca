<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\TimeEntry;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function __construct(
        protected ?FxService $fxService = null // nullable por si no lo inyectan en pruebas
    ) {}

    /**
     * Preview de nómina por empleado y rango.
     */
    public function previewEmployee(int $employeeId, string $from, string $to): array
    {
        $employee = \App\Models\Employee::with('position')->findOrFail($employeeId);

        // -------------------------------
        // 1) Traer marcaciones del rango
        // -------------------------------
        $rows = \App\Models\TimeEntry::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('work_date', [$from, $to])
            ->orderBy('work_date')
            ->get(['work_date','check_in','check_out','notes']);

        // ---------------------------------------
        // 2) Feriados pagados (tabla holidays.paid)
        // ---------------------------------------
        $holidayDates = DB::table('holidays')
            ->whereBetween('date', [$from, $to])
            ->where('paid', 1)
            ->pluck('date')
            ->all(); // array 'YYYY-MM-DD'

        // ----------------------------------------------------------------
        // 3) Agrupar por día y consolidar intervalos (merge overlappings)
        // ----------------------------------------------------------------
        $byDayIntervals = [];   // 'YYYY-MM-DD' => [ ['in'=>..., 'out'=>...], ... ]
        foreach ($rows as $r) {
            $workDate = $r->work_date instanceof \Carbon\Carbon
                ? $r->work_date->toDateString()
                : (string) $r->work_date;

            $checkIn = $r->check_in instanceof \Carbon\Carbon
                ? $r->check_in->format('Y-m-d H:i:s')
                : (string) $r->check_in;

            $checkOut = $r->check_out instanceof \Carbon\Carbon
                ? $r->check_out->format('Y-m-d H:i:s')
                : (string) $r->check_out;

            if ($checkIn === '' || $checkOut === '') {
                continue;
            }

            $byDayIntervals[$workDate] ??= [];
            $byDayIntervals[$workDate][] = ['in' => $checkIn, 'out' => $checkOut];
        }

        // Consolidado para respuesta "entries" y horas por día
        $entries = [];           // bloques consolidados por día
        $byDayHours = [];        // 'YYYY-MM-DD' => total horas (sin sobreconteo)

        foreach ($byDayIntervals as $date => $intervals) {
            $merged = $this->mergeDailyIntervals(
                array_map(fn($x) => ['in' => $x['in'], 'out' => $x['out']], $intervals)
            );

            $sumH = 0.0;
            foreach ($merged as $m) {
                $h = $this->hoursBetween($m['in'], $m['out']);
                $sumH += $h;
            }

            // Un solo bloque representativo (08:00–16:00, por ejemplo)
            if (!empty($merged)) {
                $first = reset($merged);
                $last  = end($merged);
                $entries[] = [
                    'work_date' => $date . ' 00:00:00',
                    'check_in'  => $first['in'],
                    'check_out' => $last['out'],
                    'notes'     => 'consolidado(' . count($merged) . ' bloques)',
                    'hours'     => round($sumH, 2),
                ];
            }

            $byDayHours[$date] = round($sumH, 2);
        }

        // ----------------------------------------------------------------
        // 4) Parámetros/ajustes de planilla (thresholds, etc.)
        // ----------------------------------------------------------------
        $settings           = $this->getPayrollSettings();
        $overtimeThreshold  = (float)($settings['overtime_threshold'] ?? 8.0);
        $holidayMultiplier  = 2.0;
        $overtimeMultiplier = 1.5;

        // ----------------------------------------------------------------
        // 5) Desglose por día (normal, overtime, holiday)
        // ----------------------------------------------------------------
        $daily  = [];
        $normal = 0.0;
        $ot15   = 0.0;
        $hol2   = 0.0;

        foreach ($byDayHours as $date => $hours) {
            $isHoliday = in_array($date, $holidayDates, true);

            $dNormal = 0.0;
            $dOT     = 0.0;
            $dHol    = 0.0;

            if ($isHoliday) {
                // Todo el día se paga como feriado (no suma normal/extra)
                $dHol = $hours;
            } else {
                if ($hours > $overtimeThreshold) {
                    $dNormal = $overtimeThreshold;
                    $dOT     = $hours - $overtimeThreshold;
                } else {
                    $dNormal = $hours;
                }
            }

            $normal += $dNormal;
            $ot15   += $dOT;
            $hol2   += $dHol;

            $daily[] = [
                'date'           => $date . ' 00:00:00',
                'is_holiday'     => $isHoliday,
                'hours_total'    => round($hours, 2),
                'hours_normal'   => round($dNormal, 2),
                'hours_holiday'  => round($dHol, 2),
                'hours_overtime' => round($dOT, 2),
            ];
        }

        // Totales desde daily (evita drift)
        $acc = [
            'hours_sum'          => 0.0,
            'normal_hours'       => 0.0,
            'holiday_hours'      => 0.0,
            'overtime_hours'     => 0.0,
            'overtime_threshold' => $overtimeThreshold,
        ];
        foreach ($daily as $d) {
            $acc['hours_sum']     += (float)($d['hours_total']    ?? 0);
            $acc['normal_hours']  += (float)($d['hours_normal']   ?? 0);
            $acc['holiday_hours'] += (float)($d['hours_holiday']  ?? 0);
            $acc['overtime_hours']+= (float)($d['hours_overtime'] ?? 0);
        }
        $totals = [
            'hours_sum'          => round($acc['hours_sum'], 2),
            'normal_hours'       => round($acc['normal_hours'], 2),
            'holiday_hours'      => round($acc['holiday_hours'], 2),
            'overtime_hours'     => round($acc['overtime_hours'], 2),
            'overtime_threshold' => $acc['overtime_threshold'],
        ];

        // ----------------------------------------------------------------
        // 6) Tarifa/hora desde puesto y a CRC
        // ----------------------------------------------------------------
        $pos      = $employee->position;
        $currency = $pos->default_salary_currency ?? 'CRC';

        $hourlyRate = 0.0;
        if ($pos?->salary_type === 'hourly') {
            $hourlyRate = (float) ($pos->default_salary_amount ?? $pos->base_hourly_rate ?? 0);
        } elseif ($pos?->salary_type === 'monthly' && $pos->default_salary_amount) {
            // mensual → hora (aprox. 173.33 h/mes)
            $hourlyRate = (float) $pos->default_salary_amount / 173.33;
        }

        $baseCurrency   = 'CRC';
        $hourlyRateBase = $hourlyRate;

        if ($currency !== $baseCurrency) {
            try {
                if ($this->fxService && method_exists($this->fxService, 'rate')) {
                    $fx = $this->fxService->rate($currency, $baseCurrency, $from) ?? 540.0;
                } elseif ($this->fxService && method_exists($this->fxService, 'getRate')) {
                    $fx = $this->fxService->getRate($currency, $baseCurrency, $from) ?? 540.0;
                } else {
                    $fx = 540.0;
                }
            } catch (\Throwable $e) {
                $fx = 540.0;
            }

            $hourlyRateBase = $hourlyRate * $fx; // pasamos tarifa a CRC
            $currency       = $baseCurrency;     // desde aquí trabajamos en CRC
        }

        // ----------------------------------------------------------------
        // 7) Montos (usa SIEMPRE $hourlyRateBase)
        // ----------------------------------------------------------------
        $regularAmount  = $normal * $hourlyRateBase;
        $holidayAmount  = $hol2  * $hourlyRateBase * $holidayMultiplier;
        $overtimeAmount = $ot15  * $hourlyRateBase * $overtimeMultiplier;

        $money = [
            'base_currency'   => $baseCurrency,
            'hourly_rate'     => round($hourlyRateBase, 6),
            'normal_amount'   => round($regularAmount, 2),
            'holiday_amount'  => round($holidayAmount, 2),
            'overtime_amount' => round($overtimeAmount, 2),
            'total_amount'    => round($regularAmount + $holidayAmount + $overtimeAmount, 2),

            // metadatos de salario fuente (sin convertir)
            'salary_source'   => 'position',
            'salary_type'     => $pos?->salary_type ?? 'hourly',
            'salary_amount'   => (float) ($pos?->default_salary_amount ?? $pos?->base_hourly_rate ?? 0),
            'salary_currency' => $pos?->default_salary_currency ?? 'CRC',

            'params'          => [
                'overtime_threshold' => $overtimeThreshold,
            ],
        ];

        // ----------------------------------------------------------------
        // 8) Embargos (tabla garnishments)
        //     - activos
        //     - que intersecten con el rango (start_date <= to) AND (end_date IS NULL OR end_date >= from)
        //     - cálculo sobre el bruto del preview
        // ----------------------------------------------------------------
      
        
        // ---------------- Garnishments (con tope por empleado) ----------------
$gross = (float)($money['total_amount'] ?? ($regularAmount + $holidayAmount + $overtimeAmount));
$net   = $gross;

// Lee el tope desde el empleado: ej. 0.5 = 50%. Si viene null, usa 0.5 por defecto.
$capPercent = (float)($employee->garnish_cap_rate ?? 0.5);
$capPercent = max(0.0, min($capPercent, 1.0)); // clamp 0..1

// Monto máximo embargable en el periodo
$capAmount   = round($gross * $capPercent, 2);
$capRemain   = $capAmount;

// Trae embargos activos dentro del rango
$garnishments = \DB::table('garnishments')
    ->where('employee_id', $employee->id)
    ->where('active', 1)
    ->whereDate('start_date', '<=', $to)
    ->where(function($q) use ($from) {
        $q->whereNull('end_date')->orWhere('end_date', '>=', $from);
    })
    ->orderBy('priority', 'asc')
    ->get(['id','order_no','mode','value','start_date','end_date','priority','active']);

$gItems = [];
$gTotal = 0.0;

foreach ($garnishments as $g) {
    if ($capRemain <= 0 || $net <= 0) {
        break;
    }

    // 1) Calcular deducción base
    $deduction = 0.0;
    if ($g->mode === 'percent') {
        // porcentaje del bruto del periodo
        $deduction = round($gross * ((float)$g->value) / 100, 2);
    } else {
        // monto fijo (en CRC en tu esquema actual)
        $deduction = (float)$g->value;
    }

    // 2) Aplicar topes: no más que el cap restante ni más que el neto restante
    $before = $deduction;
    $deduction = min($deduction, $capRemain, $net);

    $capped = ($deduction < $before);

    // 3) Registrar
    $gItems[] = [
        'id'         => $g->id,
        'order_no'   => $g->order_no,
        'mode'       => $g->mode,                      // 'percent' | 'amount'
        'value'      => is_null($g->value) ? null : (float)$g->value,
        'start_date' => $g->start_date ? (string)$g->start_date . ' 00:00:00' : null,
        'end_date'   => $g->end_date   ? (string)$g->end_date   . ' 00:00:00' : null,
        'priority'   => (int) $g->priority,
        'active'     => (bool) $g->active,
        'deduction'  => round($deduction, 2),
        'capped'     => $capped,
    ];

    // 4) Acumular y actualizar límites
    $gTotal   += $deduction;
    $capRemain = max(0.0, $capRemain - $deduction);
    $net       = max(0.0, $net - $deduction);
}

// Actualiza bloque money con totales netos y cap info
$money['garnishments_total'] = round($gTotal, 2);
$money['net_amount']         = round($net, 2);
$money['garnish_cap']        = [
    'percent'      => $capPercent,         // ej. 0.5
    'cap_amount'   => round($capAmount, 2),// monto máximo embargable del periodo
    'used'         => round($gTotal, 2),   // cuánto del tope se usó
    'remaining'    => round($capRemain, 2),
];


// --- Tope global a embargos (ej. 50% del bruto) ---
/*$capRate = (float)($settings['garnish_cap_rate'] ?? 0.5);
$cap     = round($gross * $capRate, 2);*/

$capRate = is_null($employee->garnish_cap_rate)
    ? (float) config('payroll.garnish_cap_default')
    : (float) $employee->garnish_cap_rate;

$cap = round($gross * $capRate, 2);


// Aplica prioridad ascendente; si empatan, por id
usort($gItems, function($a, $b) {
    $pa = (int)($a['priority'] ?? 9999);
    $pb = (int)($b['priority'] ?? 9999);
    if ($pa === $pb) {
        return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
    }
    return $pa <=> $pb;
});

$applied = 0.0;
foreach ($gItems as &$item) {
    $orig = (float)$item['deduction'];
    $room = max(0.0, $cap - $applied);
    $take = min($orig, $room);

    $item['capped']    = ($take < $orig);
    $item['deduction'] = round($take, 2);

    $applied += $take;
}
unset($item);

$gTotal = round($applied, 2);
$net    = round($gross - $gTotal, 2);

// persiste en money
$money['garnishments_total'] = $gTotal;
$money['net_amount']         = $net;




        // ------------------------------------------------------------
        // 9) Respuesta final
        // ------------------------------------------------------------
        return [
            'ok'       => true,
            'stage'    => 'preview_ready',
            'employee' => [
                'id'          => $employee->id,
                'code'        => $employee->code,
                'first_name'  => $employee->first_name,
                'last_name'   => $employee->last_name,
                'position_id' => $employee->position_id,
                'status'      => $employee->status,
            ],
            'range'   => ['from' => $from, 'to' => $to],
            'entries' => array_values($entries),
            'daily'   => array_values($daily),
            'totals'  => $totals,
            'money'   => array_merge($money, [
                'garnishments_total' => round($gTotal, 2),
                'net_amount'         => round($net, 2),
            ]),
            'garnishments' => $gItems,
        ];
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Fusiona intervalos de un día (in/out) y devuelve bloques no solapados.
     * Cada item: ['in' => 'Y-m-d H:i:s', 'out' => 'Y-m-d H:i:s']
     * Retorna:   [['in' => ..., 'out' => ...], ...] ordenados
     */
    private function mergeDailyIntervals(array $intervals): array
    {
        if (empty($intervals)) {
            return [];
        }

        // Ordenar por inicio
        usort($intervals, function ($a, $b) {
            return strcmp($a['in'], $b['in']);
        });

        $merged = [];
        $current = $intervals[0];

        for ($i = 1; $i < count($intervals); $i++) {
            $next = $intervals[$i];

            if ($next['in'] <= $current['out']) {
                // solape: extender fin
                if ($next['out'] > $current['out']) {
                    $current['out'] = $next['out'];
                }
            } else {
                // no solape: guardar bloque y continuar
                $merged[] = $current;
                $current = $next;
            }
        }
        $merged[] = $current;

        return $merged;
    }

    /**
     * Horas entre dos timestamps 'Y-m-d H:i:s'
     */
    private function hoursBetween(string $from, string $to): float
    {
        $a = strtotime($from);
        $b = strtotime($to);
        if ($a === false || $b === false) return 0.0;
        return max(0, round(($b - $a) / 3600, 2));
    }

    /**
     * Ajustes básicos de planilla.
     */
    private function getPayrollSettings(): array
    {
        try {
            $row = DB::table('payroll_settings')->orderByDesc('id')->first();
            if ($row) {
                return [
                    'workday_hours'      => (float)($row->workday_hours ?? 8),
                    'overtime_threshold' => (float)($row->overtime_threshold ?? 8),
                    'base_currency'      => (string)($row->base_currency ?? 'CRC'),
                    'garnish_cap_rate'   => (float)($row->garnish_cap_rate ?? 0.5),

                ];
            }
        } catch (\Throwable $e) {
            // Si no existe la tabla o error → defaults
        }

        return [
            'workday_hours'      => 8.0,
            'overtime_threshold' => 8.0,
            'base_currency'      => 'CRC',
            'garnish_cap_rate'   => 0.5,

        ];
    }
}
