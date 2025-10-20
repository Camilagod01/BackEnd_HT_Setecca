<?php

namespace App\Http\Requests\SickLeave;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use App\Models\SickLeave;

class UpdateSickLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Si llega start/end y no total_days, lo recalculamos
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

        if ($this->filled('coverage_percent')) {
            $this->merge(['coverage_percent' => (float) $this->input('coverage_percent')]);
        }
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['sometimes', 'integer', 'exists:employees,id'],
            'start_date'  => ['sometimes', 'date'],
            'end_date'    => ['sometimes', 'date', 'after_or_equal:start_date'],
            'total_days'  => ['sometimes', 'integer', 'min:1', 'max:366'],

            'provider' => ['sometimes', Rule::in(['CCSS','INS','OTHER'])],
            'coverage_percent' => ['sometimes', 'numeric', 'min:0', 'max:100'],

            'status' => ['sometimes', Rule::in(['pending','approved','rejected'])],
            'notes'  => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $employeeId = (int) ($this->input('employee_id') ?? optional($this->route('sick_leave'))->employee_id);
            $start = $this->input('start_date') ?? optional($this->route('sick_leave'))->start_date?->format('Y-m-d');
            $end   = $this->input('end_date')   ?? optional($this->route('sick_leave'))->end_date?->format('Y-m-d');
            $ignoreId = optional($this->route('sick_leave'))->id;

            if ($employeeId && $start && $end) {
                $overlap = SickLeave::overlapping($employeeId, $start, $end, $ignoreId)->exists();
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
            'end_date.after_or_equal' => 'La fecha final debe ser posterior o igual a la fecha de inicio.',
            'provider.in'             => 'Proveedor inválido.',
            'status.in'               => 'Estado inválido.',
        ];
    }
}

