<?php

namespace App\Services;

use App\Models\TimeEntry;
use App\Models\SickLeave;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

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
                'extra_day'  => 0.0,
                'extra_week' => 0.0,
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
        $totalWorked = 0.0;
        $totalExtraDay = 0.0;
        $byDay = [];
        $weeklyWorked = [];
        $sick50Days = 0;
        $sick0Days  = 0;

        foreach ($period as $day) {
            $date = $day->toDateString();
            $dow  = $day->dayOfWeekIso; // 1..7

            // Jornada por defecto (luego parametrizamos con payroll_settings)
            $expected = in_array($dow, [1,2,3,4]) ? 10.0 : ($dow === 5 ? 8.0 : 0.0);

            // Horas trabajadas crudas para ese día (antes de incapacidad)
            $rawWorked = $this->sumWorkedHoursForDate(
                $byDate->get($date, collect()),
                $date,
                $hasCheckIn,
                $hasCheckOut,
                $hasHoursWorked
            );

            $sick = $sickByDate[$date] ?? null;
            $sickType  = $sick['type']  ?? null;
            $sickNotes = $sick['notes'] ?? null;

            // Ajuste por incapacidad
            if ($sickType === '0pct') {
                $sick0Days++;
                $worked = 0.0; // No cuenta
            } else {
                $worked = $rawWorked; // 50% cuenta normal en métricas
                if ($sickType === '50pct') {
                    $sick50Days++;
                }
            }

            $extraDay = max(0.0, $worked - $expected);

            $totalWorked   += $worked;
            $totalExtraDay += $extraDay;

            $byDay[] = [
                'date'              => $date,
                'weekday'           => $day->isoFormat('ddd'),
                'expected'          => round($expected, 2),
                'worked'            => round($worked, 2),
                'extra_day'         => round($extraDay, 2),
                'sick_leave_type'   => $sickType,
                'sick_leave_notes'  => $sickNotes,
                'raw_worked'        => round($rawWorked, 2),
            ];

            $wkKey = $day->isoWeekYear . '-W' . str_pad((string)$day->isoWeek, 2, '0', STR_PAD_LEFT);
            $weeklyWorked[$wkKey] = ($weeklyWorked[$wkKey] ?? 0) + $worked; // ya ajustado
        }

        // Extra semanal (>48h)
        $weeks = [];
        $totalExtraWeek = 0.0;
        foreach ($weeklyWorked as $wk => $wh) {
            $ex = max(0.0, $wh - 48.0);
            $weeks[] = ['week' => $wk, 'worked' => round($wh, 2), 'extra_week' => round($ex, 2)];
            $totalExtraWeek += $ex;
        }

        return [
            'from'       => $fromDate,
            'to'         => $toDate,
            'total'      => round($totalWorked, 2),
            'extra_day'  => round($totalExtraDay, 2),
            'extra_week' => round($totalExtraWeek, 2),
            'sick_50pct_days' => $sick50Days,
            'sick_0pct_days'  => $sick0Days,
            'days'       => $byDay,
            'weeks'      => $weeks,
        ];
    }

    /**
     * Construye un mapa fecha => tipo ('0pct' / '50pct') para el rango dado.
     * Si coexisten, prevalece '0pct'.
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
                // si coexisten, '0pct' prevalece
                if (!isset($map[$k]) || $lv->type === '0pct') {
                    $map[$k] = [
                        'type'  => $lv->type,   // '0pct' | '50pct'
                        'notes' => $lv->notes,
                    ];
                }
            }
        }
        return $map;
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
                    if ($out->lessThan($in)) $out->addDay(); // cruce de medianoche
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
