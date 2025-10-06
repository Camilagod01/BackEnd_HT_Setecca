<?php
namespace App\Http\Controllers;

use App\Models\TimeEntry;
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
                'days'       => [],
                'weeks'      => [],
            ];
        }

        // Normaliza fechas
        $fromDate = Carbon::parse($from)->toDateString();
        $toDate   = Carbon::parse($to)->toDateString();

        // SELECT defensivo
        $cols = ['id', 'employee_id', 'work_date'];
        if ($hasCheckIn)     $cols[] = 'check_in';
        if ($hasCheckOut)    $cols[] = 'check_out';
        if ($hasHoursWorked) $cols[] = 'hours_worked';

        $rows = TimeEntry::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('work_date', [$fromDate, $toDate])
            ->orderBy('work_date')->orderBy('id')
            ->get($cols);

       $byDate = $rows->groupBy(function ($r) {
    // Normaliza siempre a 'YYYY-MM-DD', sea string, timestamp o Carbon
    return \Carbon\Carbon::parse($r->work_date)->toDateString();
});


        $period = CarbonPeriod::create($fromDate, $toDate);
        $totalWorked = 0.0;
        $totalExtraDay = 0.0;
        $byDay = [];
        $weeklyWorked = [];

        foreach ($period as $day) {
            $date = $day->toDateString();
            $dow  = $day->dayOfWeekIso; // 1..7

            // Jornada: Lun–Jue 10h, Vie 8h, Sáb/Dom 0h
            $expected = in_array($dow, [1,2,3,4]) ? 10.0 : ($dow === 5 ? 8.0 : 0.0);

            $worked = $this->sumWorkedHoursForDate(
                $byDate->get($date, collect()),
                $date,
                $hasCheckIn,
                $hasCheckOut,
                $hasHoursWorked
            );

            $extraDay = max(0.0, $worked - $expected);

            $totalWorked   += $worked;
            $totalExtraDay += $extraDay;

            $byDay[] = [
                'date'      => $date,
                'weekday'   => $day->isoFormat('ddd'),
                'expected'  => round($expected, 2),
                'worked'    => round($worked, 2),
                'extra_day' => round($extraDay, 2),
            ];

            $wkKey = $day->isoWeekYear . '-W' . str_pad((string)$day->isoWeek, 2, '0', STR_PAD_LEFT);
            $weeklyWorked[$wkKey] = ($weeklyWorked[$wkKey] ?? 0) + $worked;
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
            'days'       => $byDay,
            'weeks'      => $weeks,
        ];
    }

    /**
     * Suma horas para una fecha (acepta):
     * - check_in/check_out como DATETIME (2025-10-02 07:16:00)
     * - check_in/check_out como hora (07:16:00)
     * - hours_worked (numérico)
     */
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

    /** Acepta DATETIME completo o solo hora; si es hora, la combina con $date */
    private function parseCheckField(string $date, string $value): ?Carbon
    {
        $v = trim($value);

        // Si tiene fecha y hora, parsea directo
        if (preg_match('/\d{4}-\d{2}-\d{2}/', $v) && preg_match('/\d{2}:\d{2}/', $v)) {
            try { return Carbon::parse($v); } catch (\Throwable $e) {}
        }

        // Si parece solo hora, combínala con la fecha
        return $this->parseTimeFlexible($date, $v);
    }

    /** Parser tolerante para horas (incluye am/pm en español) */
    private function parseTimeFlexible(string $date, string $time): ?Carbon
    {
        $raw = trim($time);

        // Normaliza AM/PM en español
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
