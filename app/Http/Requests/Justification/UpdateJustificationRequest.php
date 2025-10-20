<?php

namespace App\Http\Requests\Justification;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJustificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Sin políticas de roles
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['sometimes', 'exists:employees,id'],
            'date'        => ['sometimes', 'date'],
            'from_time'   => ['nullable', 'date_format:H:i'],
            'to_time'     => ['nullable', 'date_format:H:i', 'after_or_equal:from_time'],
            'type'        => ['sometimes', Rule::in(['late','early_leave','absence','other'])],
            'reason'      => ['nullable', 'string', 'max:255'],
            'notes'       => ['nullable', 'string'],
            'status'      => ['sometimes', Rule::in(['pending','approved','rejected'])],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.exists'   => 'Empleado inválido.',
            'from_time.date_format'=> 'from_time debe tener formato HH:mm.',
            'to_time.date_format'  => 'to_time debe tener formato HH:mm.',
            'to_time.after_or_equal' => 'to_time debe ser posterior o igual a from_time.',
            'type.in'              => 'Tipo inválido.',
            'status.in'            => 'Estado inválido.',
        ];
    }
}

