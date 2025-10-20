<?php

namespace Database\Factories;

use App\Models\LoanPayment;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanPaymentFactory extends Factory
{
    protected $model = LoanPayment::class;

    public function definition(): array
    {
        // Obtenemos un préstamo aleatorio (o creamos uno si no hay)
        $loan = Loan::inRandomOrder()->first() ?? Loan::factory()->create();

        $dueDate = $this->faker->dateTimeBetween('now', '+2 months');
        $amount  = $this->faker->randomFloat(2, 25_000, 150_000);

        return [
            'loan_id'    => $loan->id,
            'due_date'   => $dueDate->format('Y-m-d'),
            'amount'     => $amount,
            'status'     => $this->faker->randomElement(['pending','paid','skipped']),
            'source'     => $this->faker->randomElement(['payroll','manual']),
            'remarks'    => $this->faker->optional()->sentence(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}

