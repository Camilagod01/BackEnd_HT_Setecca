<?php

namespace Database\Factories;

use App\Models\Advance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

class AdvanceFactory extends Factory
{
    protected $model = Advance::class;

    public function definition(): array
    {
        // Tomar un empleado existente; si no hay, usar 1 (ajusta según tu DB)
        $employeeId = DB::table('employees')->inRandomOrder()->value('id') ?? 1;

        return [
            'employee_id'     => $employeeId,
            'amount'          => $this->faker->randomFloat(2, 5000, 250000),
            'currency'        => $this->faker->randomElement(['CRC','USD']),
            'granted_at'      => $this->faker->dateTimeBetween('-60 days', 'now')->format('Y-m-d'),
            'notes'           => $this->faker->optional()->sentence(),
            'status'          => $this->faker->randomElement(['pending','applied','cancelled']),
            'scheduling_json' => null,
            'created_by'      => 1,
            'updated_by'      => 1,
        ];
    }
}
