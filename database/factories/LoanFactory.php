<?php

namespace Database\Factories;

use App\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function definition(): array
    {
        // Tomamos un empleado existente; si no hay, usa 1 (ajusta si tu DB no lo tiene)
        $employeeId = DB::table('employees')->inRandomOrder()->value('id') ?? 1;

        // Fechas coherentes
        $granted = $this->faker->dateTimeBetween('-90 days', 'now');
        $grantedStr = $granted->format('Y-m-d');

        // Monto y principal (el esquema requiere principal NOT NULL)
        $amount = $this->faker->randomFloat(2, 50_000, 500_000);

        return [
            'employee_id' => $employeeId,
            'amount'      => $amount,
            'principal'   => $amount,      // 👈 requerido por la tabla
            'currency'    => $this->faker->randomElement(['CRC','USD']),
            'granted_at'  => $grantedStr,
            'start_date'  => $grantedStr,  // 👈 la tabla lo exige, alineado con granted_at
            'status'      => $this->faker->randomElement(['active','closed']),
            'notes'       => $this->faker->optional()->sentence(),

            // opcionales si existen en el esquema:
            'created_by'  => 1,
            'updated_by'  => 1,
        ];
    }
}
