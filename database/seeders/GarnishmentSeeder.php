<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;
use App\Models\Garnishment;

class GarnishmentSeeder extends Seeder
{
    public function run(): void
    {
        // Asegura que exista al menos 1 empleado
        $employee = Employee::query()->first() ?? Employee::factory()->create([
            'status' => 'active',
        ]);

        // Crea 3 embargos de prueba variados
        Garnishment::factory()->create([
            'employee_id' => $employee->id,
            'mode'        => 'percent', // % del salario
            'value'       => 15.00,
            'priority'    => 1,
            'active'      => true,
        ]);

        Garnishment::factory()->create([
            'employee_id' => $employee->id,
            'mode'        => 'amount', // monto fijo
            'value'       => 25000.00,
            'priority'    => 2,
            'active'      => true,
        ]);

        Garnishment::factory()->create([
            'employee_id' => $employee->id,
            'mode'        => 'percent',
            'value'       => 5.00,
            'priority'    => 3,
            'active'      => false, // inactivo
        ]);
    }
}
