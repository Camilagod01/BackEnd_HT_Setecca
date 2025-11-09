<?php

namespace App\Services\Payroll;

/**
 * Reglas de horas extra y feriados.
 *
 * Objetivos:
 * - Feriados pagados: horas * 2.0 (no se mezclan con extra).
 * - Horas extra diarias: > dailyThreshold, multiplicador 1.5.
 * - Horas extra semanales: si la suma semanal supera weeklyThreshold,
 *   se calculan horas extra semanales SIN volver a contar lo ya contado
 *   como extra diario (evita pago doble).
 *
 * Uso típico:
 * 1) Para cada día -> splitDay($hours, $isHoliday, $holidayPaid)
 * 2) Al cerrar la semana -> applyWeeklyOvertime($summaries)
 */
class OvertimePolicy
{
    /** @var float Horas normales por día antes de extra (ej. 8) */
    protected float $dailyThreshold;
    /** @var float Horas normales semanales antes de extra (ej. 48) */
    protected float $weeklyThreshold;
    /** @var float Multiplicador de extra diaria (ej. 1.5) */
    protected float $dailyMultiplier;
    /** @var float Multiplicador de feriado (ej. 2.0) */
    protected float $holidayMultiplier;

    public function __construct(
        float $dailyThreshold = 8.0,
        float $weeklyThreshold = 48.0,
        float $dailyMultiplier = 1.5,
        float $holidayMultiplier = 2.0
    ) {
        $this->dailyThreshold   = $dailyThreshold;
        $this->weeklyThreshold  = $weeklyThreshold;
        $this->dailyMultiplier  = $dailyMultiplier;
        $this->holidayMultiplier = $holidayMultiplier;
    }

    /**
     * Separa un día en horas normales / extra diarias / feriado.
     * NOTA: si es feriado pagado, todas las horas van a "holiday_hours"
     * con multiplicador 2.0 y NO se consideran para extra diaria.
     *
     * @return array{
     *   normal_hours: float,
     *   daily_ot_hours: float,
     *   holiday_hours: float,
     *   daily_ot_multiplier: float,
     *   holiday_multiplier: float
     * }
     */
    public function splitDay(
        float $workedHours,
        bool $isHoliday,
        bool $holidayPaid
    ): array {
        $worked = max(0.0, $workedHours);

        // Feriado pagado -> todo se paga con multiplicador de feriado
        if ($isHoliday && $holidayPaid) {
            return [
                'normal_hours'         => 0.0,
                'daily_ot_hours'       => 0.0,
                'holiday_hours'        => $worked,
                'daily_ot_multiplier'  => $this->dailyMultiplier,
                'holiday_multiplier'   => $this->holidayMultiplier,
            ];
        }

        // Día normal -> separar extra diaria por umbral
        $normal = min($worked, $this->dailyThreshold);
        $dailyOT = max(0.0, $worked - $this->dailyThreshold);

        return [
            'normal_hours'         => $normal,
            'daily_ot_hours'       => $dailyOT,
            'holiday_hours'        => 0.0,
            'daily_ot_multiplier'  => $this->dailyMultiplier,
            'holiday_multiplier'   => $this->holidayMultiplier,
        ];
    }

    /**
     * Calcula extra semanal sin doble conteo.
     * Recibe el arreglo (por 7 días típicamente) de summaries devueltos por splitDay().
     *
     * Lógica:
     * - Suma horas normales + extra diarias + feriado (todas son horas trabajadas).
     * - Determina exceso sobre weeklyThreshold.
     * - Extra semanal efectiva = exceso - (horas ya marcadas como extra diaria),
     *   y no menor que 0. Así evitamos pagar dos veces las mismas horas.
     *
     * @param array<int, array{
     *   normal_hours: float,
     *   daily_ot_hours: float,
     *   holiday_hours: float,
     *   daily_ot_multiplier: float,
     *   holiday_multiplier: float
     * }> $daySummaries
     *
     * @return array{
     *   week_total_hours: float,
     *   weekly_ot_hours: float,
     *   counted_daily_ot_hours: float
     * }
     */
    public function applyWeeklyOvertime(array $daySummaries): array
    {
        $totalWorked = 0.0;
        $dailyOTSum  = 0.0;

        foreach ($daySummaries as $d) {
            $totalWorked += ($d['normal_hours'] ?? 0.0)
                         +  ($d['daily_ot_hours'] ?? 0.0)
                         +  ($d['holiday_hours'] ?? 0.0);
            $dailyOTSum  += ($d['daily_ot_hours'] ?? 0.0);
        }

        $excessOverWeekly = max(0.0, $totalWorked - $this->weeklyThreshold);

        // Extra semanal neta (sin volver a contar la extra diaria)
        $weeklyOT = max(0.0, $excessOverWeekly - $dailyOTSum);

        return [
            'week_total_hours'     => $totalWorked,
            'weekly_ot_hours'      => $weeklyOT,
            'counted_daily_ot_hours' => $dailyOTSum,
        ];
    }

    /** Accesores por si necesitas leer configuraciones en controladores/servicios */
    public function dailyThreshold(): float { return $this->dailyThreshold; }
    public function weeklyThreshold(): float { return $this->weeklyThreshold; }
    public function dailyMultiplier(): float { return $this->dailyMultiplier; }
    public function holidayMultiplier(): float { return $this->holidayMultiplier; }
}
