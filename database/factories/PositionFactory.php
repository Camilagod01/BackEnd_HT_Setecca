<?php

namespace Database\Factories;

use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        // salary_type: 'monthly' o 'hourly'
        $salaryType = $this->faker->randomElement(['monthly', 'hourly']);

        // si es monthly, usar un monto mensual; si es hourly, monto por hora
        $amount = $salaryType === 'monthly'
            ? $this->faker->numberBetween(400000, 1500000)   // CRC/mes
            : $this->faker->randomFloat(2, 1500, 8000);      // CRC/hora

        return [
            'code'                     => strtoupper($this->faker->bothify('POS-###')),
            'name'                     => $this->faker->jobTitle(),
            'salary_type'              => $salaryType,                 // 'monthly' | 'hourly'
            'default_salary_amount'    => $amount,
            'default_salary_currency'  => 'CRC',                       // o 'USD' si lo necesitas en tests
            // compat legacy (si tu modelo aún los tiene “fillable”):
            'base_hourly_rate'         => $salaryType === 'hourly' ? $amount : null,
            'currency'                 => 'CRC',
            'is_active'                => true,
        ];
    }
}
