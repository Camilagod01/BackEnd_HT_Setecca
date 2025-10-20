<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Genera planes de pagos para préstamos.
 *
 * Modos soportados:
 *  - 'next'  : 1 sola cuota en la próxima fecha estimada (por defecto granted_at + 14 días).
 *  - 'nth'   : 1 sola cuota en la N-ésima fecha estimada (granted_at + (n * intervalDays)).
 *  - 'custom': lista de cuotas personalizadas [{due_date:'YYYY-MM-DD', amount:123.45}, ...].
 *
 * Notas:
 *  - No depende de policies ni de módulos de planilla.
 *  - Las cuotas se generan con status 'pending' y source 'payroll' por defecto.
 *  - Valida sumatoria en 'custom' == monto total del préstamo (si se provee).
 */
class LoanSchedulerService
{
    /**
     * @param array $loan Datos base del préstamo. Esperado:
     *  [
     *    'amount'      => float|numeric-string (monto total),
     *    'currency'    => 'CRC'|'USD',
     *    'granted_at'  => 'YYYY-MM-DD',
     *  ]
     * @param array $options Opciones del plan. Ejemplos:
     *  - ['mode'=>'next', 'firstDueDate'=>null, 'intervalDays'=>14]
     *  - ['mode'=>'nth',  'n'=>3, 'intervalDays'=>14]
     *  - ['mode'=>'custom','installments'=>[['due_date'=>'2025-11-15','amount'=>50000], ...]]
     *
     * @return array lista de cuotas: [['due_date'=>'YYYY-MM-DD','amount'=>123.45,'status'=>'pending','source'=>'payroll','remarks'=>null],...]
     */
    public function generatePlan(array $loan, array $options): array
    {
        $mode = $options['mode'] ?? 'next';
        $amountTotal = (float) ($loan['amount'] ?? 0);
        $grantedAt = Carbon::parse($loan['granted_at'] ?? now());
        $currency = $loan['currency'] ?? 'CRC';

        // Normalizar opciones comunes
        $intervalDays = (int)($options['intervalDays'] ?? 14); // quincenal por defecto

        switch ($mode) {
            case 'next':
                // 1 sola cuota: primera fecha estimada (puedes forzar con firstDueDate)
                $firstDue = isset($options['firstDueDate'])
                    ? Carbon::parse($options['firstDueDate'])
                    : $grantedAt->copy()->addDays($intervalDays);

                return [[
                    'due_date' => $firstDue->toDateString(),
                    'amount'   => $this->round2($amountTotal),
                    'status'   => 'pending',
                    'source'   => 'payroll',
                    'remarks'  => null,
                ]];

            case 'nth':
                // 1 sola cuota en la N-ésima fecha
                $n = max(1, (int)($options['n'] ?? 1));
                $firstDue = isset($options['firstDueDate'])
                    ? Carbon::parse($options['firstDueDate'])
                    : $grantedAt->copy()->addDays($intervalDays);

                $targetDue = $firstDue->copy()->addDays(($n - 1) * $intervalDays);

                return [[
                    'due_date' => $targetDue->toDateString(),
                    'amount'   => $this->round2($amountTotal),
                    'status'   => 'pending',
                    'source'   => 'payroll',
                    'remarks'  => "N-ésimo pago: {$n}",
                ]];

            case 'custom':
                // Lista personalizada de cuotas
                $installments = $options['installments'] ?? [];
                if (!is_array($installments) || empty($installments)) {
                    throw new \InvalidArgumentException("Custom mode requiere 'installments'.");
                }

                $plan = [];
                $sum = 0.0;
                foreach ($installments as $i) {
                    if (!isset($i['due_date'], $i['amount'])) {
                        throw new \InvalidArgumentException("Cada cuota custom requiere due_date y amount.");
                    }
                    $due = Carbon::parse($i['due_date']);
                    $amt = $this->round2((float)$i['amount']);
                    $sum += $amt;
                    $plan[] = [
                        'due_date' => $due->toDateString(),
                        'amount'   => $amt,
                        'status'   => 'pending',
                        'source'   => 'payroll',
                        'remarks'  => $i['remarks'] ?? null,
                    ];
                }

                // Validación opcional: la sumatoria debe coincidir con el monto del préstamo
                if ($amountTotal > 0 && $this->round2($sum) !== $this->round2($amountTotal)) {
                    throw new \InvalidArgumentException("La suma de cuotas ({$sum}) no coincide con el monto del préstamo ({$amountTotal}).");
                }

                // Ordenar por fecha por si vienen desordenadas
                usort($plan, fn($a, $b) => strcmp($a['due_date'], $b['due_date']));

                return $plan;

            default:
                throw new \InvalidArgumentException("Modo de programación no soportado: {$mode}");
        }
    }

    private function round2(float $v): float
    {
        return round($v, 2);
    }
}
