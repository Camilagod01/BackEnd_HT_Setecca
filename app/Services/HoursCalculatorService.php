<?php

namespace App\Services;

use App\Models\TimeEntry;
use App\Models\SickLeave;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class HoursCalculatorService
{
    public function calcForEmployee(int $employeeId, string $from, string $to): array
    {
        $hasWorkDate     = Schema::hasColumn('time_entries', 'work_date');
        $hasCheckIn      = Schema::hasColumn('time_entries', 'check_in');
        $hasCheckOut     = Schema::hasColumn('time_entries', 'check_out');
        $hasHoursWorked  = Schema::hasColumn('time_entries', 'hours_worked');

        if (!$hasWorkDate) {
            return [
                'from'       => $from,
                'to'         => $to,
                'total'      => 0.0,
                'extra_day'  => 0.0, // usaremos esto como horas 1.5x
                'extra_week' => 0.0, // y esto como horas 2.0x (domingo/feriado + exceso semanal)
                'sick_50pct_days' => 0,
                'sick_0pct_days'  => 0,
                'days'       => [],
                'weeks'      => [],
            ];
        }

        $fromDate = Carbon::parse($from)->toDateString();
        $toDate   = Carbon::parse($to)->toDateString();

        // ===== Incapacidades del rango =====
        $sickByDate = $this->buildSickDateMap($employeeId, $fromDate, $toDate);

        // ===== Feriados del rango =====
        $holidaySet = $this->buildHolidaySet($fromDate, $toDate); // set de 'Y-m-d'

        $cols = ['id', 'employee_id', 'work_date'];
        if ($hasCheckIn)     $cols[] = 'check_in';
        if ($hasCheckOut)    $cols[] = 'check_out';
        if ($hasHoursWorked) $cols[] = 'hours_worked';

        $rows = TimeEntry::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('work_date', [$fromDate, $toDate])
            ->orderBy('work_date')->orderBy('id')
            ->get($cols);

        $byDate = $rows->groupBy(fn ($r) => Carbon::parse($r->work_date)->toDateString());

        $period = CarbonPeriod::create($fromDate, $toDate);

        $sumWorkedAll = 0.0;        // horas totales (para referencia)
        $bucketReg    = 0.0;        // 1.0x
        $bucket15     = 0.0;        // 1.5x (diario)
        $bucket20     = 0.0;        // 2.0x (domingo/feriado + exceso semanal)
        $byDay        = [];
        $weeklyWorkedNonHoliday = []; // solo horas de días NO domingo/feriado (reg + 1.5), para el umbral 48h

        $sick50Days = 0;
        $sick0Days  = 0;

        foreach ($period as $day) {
            $date = $day->toDateString();
            $dow  = $day->dayOfWeekIso; // 1..7 (1=Mon,7=Sun)

            $expected = in_array($dow, [1,2,3,4]) ? 10.0 : ($dow === 5 ? 8.0 : 0.0);

            // Horas crudas del día
            $rawWorked = $this->sumWorkedHoursForDate(
                $byDate->get($date, collect()),
                $date,
                $hasCheckIn,
                $hasCheckOut,
                $hasHoursWorked
            );

            $isHoliday = isset($holidaySet[$date]);
            $isSunday  = ($dow === 7);

            $sick = $sickByDate[$date] ?? null;
            $sickType  = $sick['type']  ?? null;
            $sickNotes = $sick['notes'] ?? null;

            // Ajuste por incapacidad
            if ($sickType === '0pct') {
                $sick0Days++;
                $worked = 0.0;
            } else {
                $worked = $rawWorked;
                if ($sickType === '50pct') {
                    $sick50Days++;
                }
            }

            $sumWorkedAll += $worked;

            $regToday  = 0.0;
            $x15Today  = 0.0;
            $x20Today  = 0.0;

            // REGLAS:
            if ($worked > 0) {
                if ($isSunday || $isHoliday) {
                    // 💡 Feriado o domingo TRABAJADO: todo a 2x
                    $x20Today = $worked;
                } else {
                    // Día laborable trabajado: primeras 8h a 1x, resto a 1.5x
                    $regToday = min($worked, 8.0);
                    $x15Today = max(0.0, $worked - 8.0);
                }
            } else {
                // 💡 No hubo horas registradas
                if ($isHoliday && $sickType === null) {
                    // Feriado NO TRABAJADO, SIN incapacidad: se paga a 1x como si se hubiera laborado
                    // Jornada “esperada” que ya calculaste en $expected (L-J 10h, V 8h, S/D 0h)
                    $regToday = $expected;
                    // (No computa a 48h semanales; ver acumulación semanal más abajo)
                }
            }

            // Acumular buckets diarios
            $bucketReg += $regToday;
            $bucket15  += $x15Today;
            $bucket20  += $x20Today;

            // Para la regla semanal 48h solo cuentan días no domingo/feriado (reg + 1.5)
            $wkKey = $day->isoWeekYear . '-W' . str_pad((string)$day->isoWeek, 2, '0', STR_PAD_LEFT);
            $countsFor48h = (!$isSunday && !$isHoliday && ($worked > 0)); // sólo días laborables con trabajo real
            if ($countsFor48h) {
                $weeklyWorkedNonHoliday[$wkKey] = ($weeklyWorkedNonHoliday[$wkKey] ?? 0) + ($regToday + $x15Today);
            }

            // Guardar detalle del día (agrega flag de “feriado pagado sin trabajar” si quieres)
            $byDay[] = [
                'date'         => $date,
                'weekday'      => $day->isoFormat('ddd'),
                'expected'     => round($expected, 2),
                'worked'       => round($worked, 2),
                'regular_1x'   => round($regToday, 2),
                'overtime_15x' => round($x15Today, 2),
                'double_20x'   => round($x20Today, 2),
                'is_sunday'    => $isSunday,
                'is_holiday'   => $isHoliday,
                'holiday_paid_without_work' => ($isHoliday && $worked==0 && $sickType===null && $regToday>0),
                'sick_leave_type'  => $sickType,
                'raw_worked'   => round($rawWorked, 2),
            ];
        }

        // ===== Extra semanal (>48h) sobre días NO domingo/feriado =====
        $weeks = [];
        foreach ($weeklyWorkedNonHoliday as $wk => $wh) {
            $excess = max(0.0, $wh - 48.0);
            if ($excess > 0) {
                // Convertimos exceso de 1.5x -> 2.0x; si no alcanza, tomamos de regular
                $takeFrom15 = min($bucket15, $excess);
                $bucket15  -= $takeFrom15;
                $bucket20  += $takeFrom15;

                $remaining = $excess - $takeFrom15;
                if ($remaining > 0) {
                    $takeFromReg = min($bucketReg, $remaining);
                    $bucketReg  -= $takeFromReg;
                    $bucket20   += $takeFromReg;
                }
            }

            $weeks[] = [
                'week'       => $wk,
                'worked'     => round($wh, 2),
                'extra_week' => round(max(0.0, $wh - 48.0), 2),
            ];
        }

        // Resultado agregado (compatibilidad con tu interfaz actual)
        return [
            'from'       => $fromDate,
            'to'         => $toDate,
            'total'      => round($sumWorkedAll, 2),
            'extra_day'  => round($bucket15, 2),  // horas 1.5x finales
            'extra_week' => round($bucket20, 2),  // horas 2.0x (domingo/feriado + exceso semanal)
            'sick_50pct_days' => $sick50Days,
            'sick_0pct_days'  => $sick0Days,
            'days'       => $byDay,
            'weeks'      => $weeks,
        ];
    }

    /**
     * Mapa fecha => tipo ('0pct' / '50pct'), prevalece '0pct' cuando coexisten.
     */
    private function buildSickDateMap(int $employeeId, string $fromDate, string $toDate): array
    {
        $leaves = SickLeave::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $toDate)
            ->whereDate('end_date', '>=', $fromDate)
            ->get(['start_date','end_date','type','notes']);

        $map = [];
        foreach ($leaves as $lv) {
            $period = CarbonPeriod::create($lv->start_date, $lv->end_date);
            foreach ($period as $d) {
                $k = $d->toDateString();
                if (!isset($map[$k]) || $lv->type === '0pct') {
                    $map[$k] = ['type' => $lv->type, 'notes' => $lv->notes];
                }
            }
        }
        return $map;
    }

    /**
     * Conjunto de feriados en el rango. Soporta columna 'date' o 'holiday_date'.
     */
    private function buildHolidaySet(string $fromDate, string $toDate): array
    {
        if (!Schema::hasTable('holidays')) return [];

        $dateCol = Schema::hasColumn('holidays', 'date') ? 'date'
                 : (Schema::hasColumn('holidays', 'holiday_date') ? 'holiday_date' : null);

        if (!$dateCol) return [];

        $rows = DB::table('holidays')
            ->whereBetween($dateCol, [$fromDate, $toDate])
            ->get([$dateCol]);

        $set = [];
        foreach ($rows as $r) {
            $set[Carbon::parse($r->{$dateCol})->toDateString()] = true;
        }
        return $set;
    }

    private function sumWorkedHoursForDate(
        Collection $entries,
        string $date,
        bool $hasCheckIn,
        bool $hasCheckOut,
        bool $hasHoursWorked
    ): float {
        $worked = 0.0;

        foreach ($entries as $e) {
            // 1) check_in/check_out
            if ($hasCheckIn && $hasCheckOut && !empty($e->check_in) && !empty($e->check_out)) {
                $in  = $this->parseCheckField($date, (string)$e->check_in);
                $out = $this->parseCheckField($date, (string)$e->check_out);
                if ($in && $out) {
                    if ($out->lessThan($in)) $out->addDay(); // cruce medianoche
                    $worked += $in->diffInMinutes($out) / 60.0;
                    continue;
                }
            }

            // 2) hours_worked como fallback
            if ($hasHoursWorked && is_numeric($e->hours_worked)) {
                $worked += (float) $e->hours_worked;
                continue;
            }
        }

        return round($worked, 2);
    }

    private function parseCheckField(string $date, string $value): ?Carbon
    {
        $v = trim($value);

        if (preg_match('/\d{4}-\d{2}-\d{2}/', $v) && preg_match('/\d{2}:\d{2}/', $v)) {
            try { return Carbon::parse($v); } catch (\Throwable $e) {}
        }

        return $this->parseTimeFlexible($date, $v);
    }

    private function parseTimeFlexible(string $date, string $time): ?Carbon
    {
        $raw = trim($time);

        $norm = mb_strtolower($raw, 'UTF-8');
        $norm = str_replace(['a. m.', 'a.m.', 'am.', ' a m '], ' am ', $norm);
        $norm = str_replace(['p. m.', 'p.m.', 'pm.', ' p m '], ' pm ', $norm);
        $norm = preg_replace('/\s+/', ' ', $norm ?? '');
        $norm = trim($norm);

        $candidates = ['H:i', 'H:i:s', 'G:i', 'G:i:s', 'g:i A', 'g:i a', 'h:i A', 'h:i a'];

        foreach ($candidates as $fmt) {
            try {
                $t = Carbon::createFromFormat($fmt, strtoupper($norm));
                if ($t !== false) {
                    return Carbon::parse("$date " . $t->format('H:i:s'));
                }
            } catch (\Throwable $e) {}
        }

        try { return Carbon::parse("$date $raw"); } catch (\Throwable $e) { return null; }
    }
}
