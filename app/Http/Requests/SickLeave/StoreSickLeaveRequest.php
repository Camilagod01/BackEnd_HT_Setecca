<?php

namespace App\Http\Requests\SickLeave;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use App\Models\SickLeave;

class StoreSickLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Normalizar fechas y calcular total_days si no viene
        $start = $this->input('start_date');
        $end   = $this->input('end_date');

        if ($start && $end && !$this->filled('total_days')) {
            try {
                $s = Carbon::parse($start)->startOfDay();
                $e = Carbon::parse($end)->startOfDay();
                $days = $s->diffInDays($e) + 1; // Si lo necesita la tabla
                $this->merge(['total_days' => max(1, $days)]);
            } catch (\Throwable $e) {
                // Ignorar; lo validan las rules
            }
        }

        // Normalizar coverage_percent
        if ($this->filled('coverage_percent')) {
            $this->merge(['coverage_percent' => (float) $this->input('coverage_percent')]);
        }
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['required', 'date', 'after_or_equal:start_date'],
            'total_days'  => ['required', 'integer', 'min:1', 'max:366'],

            'provider' => ['required', Rule::in(['CCSS','INS','OTHER'])],
            'coverage_percent' => ['required', 'numeric', 'min:0', 'max:100'],

            'status' => ['required', Rule::in(['pending','approved','rejected'])],
            'notes'  => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $employeeId = (int) $this->input('employee_id');
            $start = $this->input('start_date');
            $end   = $this->input('end_date');

            if ($employeeId && $start && $end) {
                $overlap = SickLeave::overlapping($employeeId, $start, $end)->exists();
                if ($overlap) {
                    $v->errors()->add('start_date', 'El rango se solapa con otra incapacidad del empleado.');
                    $v->errors()->add('end_date', 'El rango se solapa con otra incapacidad del empleado.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'El empleado es requerido.',
            'employee_id.exists'   => 'El empleado no existe.',
            'start_date.required'  => 'La fecha de inicio es requerida.',
            'end_date.required'    => 'La fecha final es requerida.',
            'end_date.after_or_equal' => 'La fecha final debe ser posterior o igual a la fecha de inicio.',
            'total_days.min'       => 'total_days debe ser al menos 1.',
            'provider.in'          => 'Proveedor inválido.',
            'status.in'            => 'Estado inválido.',
            'coverage_percent.max' => 'El porcentaje de cobertura no puede exceder 100.',
        ];
    }
}

