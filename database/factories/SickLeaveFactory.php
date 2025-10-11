<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\SickLeave;
use App\Models\Employee;

class SickLeaveFactory extends Factory
{
    protected $model = SickLeave::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-2 months', 'now');
        $end   = (clone $start)->modify('+'.rand(1,3).' days');

        return [
            'employee_id' => Employee::inRandomOrder()->value('id') ?? 1,
            'start_date' => $start->format('Y-m-d'),
            'end_date'   => $end->format('Y-m-d'),
            'type'       => $this->faker->randomElement(['50pct', '0pct']),
            'notes'      => $this->faker->optional()->sentence(),
        ];
    }
}
