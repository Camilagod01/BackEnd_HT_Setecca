<?php

namespace App\Http\Requests\Vacation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use App\Models\Vacation;

class StoreVacationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $start = $this->input('start_date');
        $end   = $this->input('end_date');

        if ($start && $end && !$this->filled('days')) {
            try {
                $s = Carbon::parse($start)->startOfDay();
                $e = Carbon::parse($end)->startOfDay();
                $days = $s->diffInDays($e) + 1; // Si lo necesita la tabla
                $this->merge(['days' => max(1, $days)]);
            } catch (\Throwable $e) {
                // validan las rules
            }
        }
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['required', 'date', 'after_or_equal:start_date'],
            'days'        => ['required', 'integer', 'min:1', 'max:366'],
            'status'      => ['required', Rule::in(['pending','approved','rejected'])],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $empId = (int) $this->input('employee_id');
            $start = $this->input('start_date');
            $end   = $this->input('end_date');

            if ($empId && $start && $end) {
                $overlap = Vacation::overlapping($empId, $start, $end)->exists();
                if ($overlap) {
                    $v->errors()->add('start_date', 'El rango se solapa con otras vacaciones del empleado.');
                    $v->errors()->add('end_date', 'El rango se solapa con otras vacaciones del empleado.');
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
            'days.min'             => 'Los días deben ser al menos 1.',
            'status.in'            => 'Estado inválido.',
        ];
    }
}
