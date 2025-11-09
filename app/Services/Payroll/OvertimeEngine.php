<?php

namespace App\Services\Payroll;

use DateTimeImmutable;

/**
 * OvertimeEngine: orquesta el cálculo día a día y el ajuste semanal
 * apoyándose en OvertimePolicy, sin tocar tu servicio de planilla actual.
 *
 * Flujo:
 *  - addDay(...) por cada día trabajado (en cualquier orden dentro de la semana)
 *  - finalizeWeek() para obtener el resumen con extra semanal sin doble conteo
 *
 * Notas:
 *  - La "semana" la define quien consuma este motor (por ejemplo, Lun-Dom).
 *  - Si quieres iniciar semanas en otro día, manéjalo aguas arriba (lotes de 7 días).
 */
class OvertimeEngine
{
    /** @var OvertimePolicy */
    protected OvertimePolicy $policy;

    /** @var array<int,array<string,mixed>> Detalles diarios crudos de splitDay() */
    protected array $daySummaries = [];

    /** @var array<string,array<string,mixed>> indexado por fecha (YYYY-MM-DD) */
    protected array $byDate = [];

    public function __construct(?OvertimePolicy $policy = null)
    {
        $this->policy = $policy ?? new OvertimePolicy();
    }

    /**
     * Registra un día trabajado.
     *
     * @param string|\DateTimeInterface $date  Fecha del día (YYYY-MM-DD)
     * @param float $workedHours               Horas trabajadas del día
     * @param bool $isHoliday                  Es feriado (según catálogo)
     * @param bool $holidayPaid                Ese feriado se paga como tal (x2)
     */
    public function addDay($date, float $workedHours, bool $isHoliday, bool $holidayPaid): void
    {
        $dt = $date instanceof \DateTimeInterface ? $date : new DateTimeImmutable($date);
        $key = $dt->format('Y-m-d');

        $split = $this->policy->splitDay($workedHours, $isHoliday, $holidayPaid);

        // Conservamos la fecha para mapear luego resultados por día
        $split['date'] = $key;
        $split['worked_hours'] = $workedHours;
        $split['is_holiday'] = $isHoliday;
        $split['holiday_paid'] = $holidayPaid;

        $this->daySummaries[] = $split;
        $this->byDate[$key] = $split;
    }

    /**
     * Cierra la semana y devuelve desglose completo.
     *
     * @return array{
     *   days: array<int,array{
     *     date: string,
     *     worked_hours: float,
     *     is_holiday: bool,
     *     holiday_paid: bool,
     *     normal_hours: float,
     *     daily_ot_hours: float,
     *     holiday_hours: float,
     *     daily_ot_multiplier: float,
     *     holiday_multiplier: float
     *   }>,
     *   week_total_hours: float,
     *   weekly_ot_hours: float,
     *   counted_daily_ot_hours: float,
     *   thresholds: array{
     *     daily: float,
     *     weekly: float,
     *     daily_multiplier: float,
     *     holiday_multiplier: float
     *   }
     * }
     */
    public function finalizeWeek(): array
    {
        $weekly = $this->policy->applyWeeklyOvertime($this->daySummaries);

        return [
            'days' => array_values($this->daySummaries),
            'week_total_hours' => $weekly['week_total_hours'],
            'weekly_ot_hours'  => $weekly['weekly_ot_hours'],
            'counted_daily_ot_hours' => $weekly['counted_daily_ot_hours'],
            'thresholds' => [
                'daily' => $this->policy->dailyThreshold(),
                'weekly' => $this->policy->weeklyThreshold(),
                'daily_multiplier' => $this->policy->dailyMultiplier(),
                'holiday_multiplier' => $this->policy->holidayMultiplier(),
            ],
        ];
    }

    /**
     * Limpia el estado interno para reutilizar la instancia en otra semana.
     */
    public function reset(): void
    {
        $this->daySummaries = [];
        $this->byDate = [];
    }
}
