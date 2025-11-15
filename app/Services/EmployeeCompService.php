<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Position;

class EmployeeCompService
{

    /*
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
    }*/



        public function resolve(Employee $employee): array
    {
        // Si el empleado usa el salario del puesto
        if ($employee->use_position_salary) {
            $position = $employee->position; // asumiendo relación cargada o lazy
            if (!$position instanceof Position) {
                $position = Position::find($employee->position_id);
            }

            // Política: si el puesto tiene salario mensual definido, úsalo;
            // en su defecto, cae a base_hourly_rate.
            if ($position && $position->salary_type === 'monthly') {
                return [
                    'salary_source'   => 'position',
                    'salary_type'     => 'monthly',
                    'salary_amount'   => (float)($position->default_salary_amount ?? 0),
                    'salary_currency' => (string)($position->default_salary_currency ?? 'CRC'),
                ];
            }

            // Fallback por hora
            $hourly = $position ? (float)($position->base_hourly_rate ?? 0) : 0.0;
            $curr   = $position ? (string)($position->currency ?? 'CRC') : 'CRC';

            return [
                'salary_source'   => 'position',
                'salary_type'     => 'hourly',
                'salary_amount'   => $hourly,
                'salary_currency' => $curr,
            ];
        }

        // Si NO usa el salario del puesto: usa override del empleado
        if ($employee->salary_type === 'monthly') {
            return [
                'salary_source'   => 'employee',
                'salary_type'     => 'monthly',
                'salary_amount'   => (float)($employee->salary_override_amount ?? 0),
                'salary_currency' => (string)($employee->salary_override_currency ?? 'CRC'),
            ];
        }

        // Fallback por hora para empleado
        return [
            'salary_source'   => 'employee',
            'salary_type'     => 'hourly',
            'salary_amount'   => (float)($employee->salary_override_amount ?? 0),
            'salary_currency' => (string)($employee->salary_override_currency ?? 'CRC'),
        ];
    }



public function effectiveComp(int|Employee $employee, ?string $asOf = null): array
    {
        // Normaliza: si recibimos ID, buscamos; si recibimos modelo, lo usamos
        $emp = is_int($employee)
            ? Employee::with('position')->findOrFail($employee)
            : $employee->loadMissing('position');

        $amount     = $emp->salary_amount ?? $emp->position->default_salary_amount ?? 0;
        $currency   = $emp->salary_currency ?? $emp->position->default_salary_currency ?? 'CRC';
        $salaryType = $emp->position?->salary_type ?? 'monthly';

        return [
    'salary_type'      => $salaryType,
    'salary_amount'    => (float) $amount,
    'salary_currency'  => $currency ?: 'CRC',
    'position_id'      => $emp->position_id,
    'as_of'            => $asOf ?: now()->toDateString(),
            ];

    }


}
