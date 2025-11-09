<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Garnishment;
use App\Models\Employee;

class GarnishmentFactory extends Factory
{
    protected $model = Garnishment::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'order_no'    => $this->faker->optional()->bothify('ORD-#####'),
            'mode'        => $this->faker->randomElement(['percent', 'amount']),
            'value'       => $this->faker->randomFloat(2, 1, 50), // 1–50 (si percent) o monto si 'amount'
            'start_date'  => $this->faker->date(),
            'end_date'    => $this->faker->optional()->date(),
            'priority'    => $this->faker->numberBetween(1, 3),
            'active'      => $this->faker->boolean(85),
            'notes'       => $this->faker->optional()->sentence(),
        ];
    }
}
