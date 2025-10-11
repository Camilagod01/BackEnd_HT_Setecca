<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SickLeave;
use App\Models\Employee;
use Carbon\Carbon;

class SickLeaveSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::all();

        if ($employees->isEmpty()) {
            $this->command->warn('⚠️ No hay empleados, se omite SickLeaveSeeder.');
            return;
        }

        foreach ($employees as $emp) {
            $count = rand(1, 3); // entre 1 y 3 incapacidades por empleado

            for ($i = 0; $i < $count; $i++) {
                $start = Carbon::now()->subDays(rand(10, 60))->startOfDay();
                $end   = (clone $start)->addDays(rand(1, 3));

                SickLeave::create([
                    'employee_id' => $emp->id,
                    'start_date'  => $start->format('Y-m-d'),
                    'end_date'    => $end->format('Y-m-d'),
                    'type'        => rand(0, 1) ? '50pct' : '0pct',
                    'notes'       => fake()->optional()->sentence(),
                ]);
            }
        }

        $this->command->info('Incapacidades de prueba generadas correctamente.');
    }
}
