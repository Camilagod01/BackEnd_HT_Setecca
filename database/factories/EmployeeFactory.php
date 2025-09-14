<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        static $seq = 0;
        $seq++;

        $num  = min($seq, 9999);
        $code = 'emp-' . str_pad((string)$num, 4, '0', STR_PAD_LEFT);

        return [
           // Genera emp-0001..emp-9999 únicos en la corrida
        'code'       => 'emp-' . str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'first_name' => $this->faker->firstName(),
            'last_name'  => $this->faker->lastName(),
            'email'      => $this->faker->unique()->safeEmail(),
            'position'   => $this->faker->jobTitle(),
            'status'     => 'active',
        ];
    }
}