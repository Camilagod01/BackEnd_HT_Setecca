<?php

namespace App\Http\Requests\Absence;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Absence;

class StoreAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $kind  = $this->input('kind');
        $hours = $this->input('hours');

        // Si lo necesita la tabla: normalizar horas según el tipo
        if ($kind === 'full_day') {
            // En día completo, horas no aplica
            $this->merge(['hours' => null]);
        } elseif ($kind === 'hours' && is_numeric($hours)) {
            // Normaliza a 2 decimales
            $this->merge(['hours' => number_format((float)$hours, 2, '.', '')]);
        }
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['required', 'date', 'after_or_equal:start_date'],
            'kind'        => ['required', Rule::in(['full_day','hours'])],
            // Si lo necesita la tabla: horas requerido solo cuando kind=hours
            'hours'       => [
                Rule::requiredIf(fn () => $this->input('kind') === 'hours'),
                'nullable', 'numeric', 'min:0.25', 'max:12'
            ],
            'reason'      => ['nullable', 'string', 'max:150'],
            'status'      => ['required', Rule::in(['pending','approved','rejected'])],
            'notes'       => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $empId = (int) $this->input('employee_id');
            $start = $this->input('start_date');
            $end   = $this->input('end_date');

            if ($empId && $start && $end) {
                $overlap = Absence::overlapping($empId, $start, $end)->exists();
                if ($overlap) {
                    $v->errors()->add('start_date', 'El rango se solapa con otros permisos del empleado.');
                    $v->errors()->add('end_date',   'El rango se solapa con otros permisos del empleado.');
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
            'end_date.required'    => 'La fecha de fin es requerida.',
            'end_date.after_or_equal' => 'La fecha fin debe ser posterior o igual al inicio.',
            'kind.in'              => 'Tipo de permiso inválido.',
            'hours.required'       => 'Las horas son requeridas cuando el tipo es por horas.',
            'hours.min'            => 'Las horas deben ser al menos 0.25.',
            'hours.max'            => 'Las horas no pueden exceder 12.',
            'status.in'            => 'Estado inválido.',
        ];
        }
}

