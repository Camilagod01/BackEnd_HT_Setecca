<?php

namespace Database\Seeders;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Services\LoanSchedulerService;
use Illuminate\Database\Seeder;

class LoanWithPaymentsSeeder extends Seeder
{
    public function run(): void
    {
        $scheduler = new LoanSchedulerService();

        // Crea 5 préstamos (ajusta la cantidad si quieres)
        $loans = Loan::factory()->count(5)->create();

        foreach ($loans as $loan) {
            // Para cada préstamo, generar entre 1 y 4 cuotas quincenales
            $num = rand(1, 4);

            // Si num > 1 dividimos el principal en partes; si 1, toda en una
            if ($num === 1) {
                $installments = [[
                    'due_date' => $loan->granted_at->copy()->addDays(14)->toDateString(),
                    'amount'   => (float)$loan->principal,
                    'remarks'  => 'Cuota única generada por seeder',
                ]];
            } else {
                $base = round($loan->principal / $num, 2);
                $rest = round($loan->principal - ($base * ($num - 1)), 2); // para cuadrar centavos en la última
                $installments = [];
                for ($i = 0; $i < $num; $i++) {
                    $amount = ($i === $num - 1) ? $rest : $base;
                    $due    = $loan->granted_at->copy()->addDays(14 * ($i + 1))->toDateString();
                    $installments[] = [
                        'due_date' => $due,
                        'amount'   => $amount,
                        'remarks'  => "Seeder cuota ".($i+1)." de {$num}",
                    ];
                }
            }

            // Generar plan con el servicio en modo custom (solo por consistencia)
            $plan = $scheduler->generatePlan(
                [
                    'amount'     => (float)$loan->principal, // referencia (validación opcional del servicio)
                    'currency'   => $loan->currency,
                    'granted_at' => $loan->granted_at->toDateString(),
                ],
                [
                    'mode'         => 'custom',
                    'installments' => $installments,
                ]
            );

            // Insertar cuotas
            $rows = [];
            foreach ($plan as $p) {
                $rows[] = [
                    'loan_id'    => $loan->id,
                    'due_date'   => $p['due_date'],
                    'amount'     => $p['amount'],
                    'status'     => $p['status'] ?? 'pending',
                    'source'     => $p['source'] ?? 'payroll',
                    'remarks'    => $p['remarks'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            LoanPayment::insert($rows);
        }
    }
}
