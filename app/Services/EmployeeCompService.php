<?php

namespace App\Services;

use App\Models\Employee;

class EmployeeCompService
{
    public function effectiveComp(Employee $employee): array
    {
        // Si usa salario del puesto
        if ($employee->use_position_salary && $employee->position) {
            $comp = $employee->position->defaultComp();
            if ($comp['salary_amount'] === null) {
                throw new \RuntimeException("Puesto sin salario por defecto definido.");
            }
            return ['source'=>'position'] + $comp;
        }

        // Override a nivel de empleado
        if ($employee->salary_override_amount !== null && $employee->salary_override_currency) {
            return [
                'source' => 'override',
                'salary_type' => $employee->salary_type ?? 'monthly',
                'salary_amount' => (float) $employee->salary_override_amount,
                'salary_currency' => $employee->salary_override_currency,
            ];
        }

        throw new \RuntimeException("Empleado {$employee->id} no tiene salario efectivo (puesto u override).");
    }
}
