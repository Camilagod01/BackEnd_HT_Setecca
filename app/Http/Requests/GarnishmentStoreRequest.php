<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GarnishmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ajusta a tu auth si aplica
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required','integer','exists:employees,id'],
            'order_no'    => ['nullable','string','max:50'],
            'mode'        => ['required','in:amount,percent'],
            'value'       => ['required','numeric','min:0'],
            'start_date'  => ['required','date'],
            'end_date'    => ['nullable','date','after_or_equal:start_date'],
            'priority'    => ['nullable','integer','min:1','max:5'],
            'active'      => ['nullable','boolean'],
            'notes'       => ['nullable','string'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.exists' => 'Empleado no válido.',
            'mode.in'            => "El modo debe ser 'amount' o 'percent'.",
        ];
    }
}
