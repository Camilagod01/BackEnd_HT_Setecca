<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'code'        => $this->faker->unique()->bothify('emp-####'),
            'first_name'  => $this->faker->firstName(),
            'last_name'   => $this->faker->lastName(),
            'email'       => $this->faker->unique()->safeEmail(),
            // OJO: NO usar 'position' (texto). Usar la FK:
            'position_id' => Position::factory(),
            'status'      => 'active',
        ];
    }
}
