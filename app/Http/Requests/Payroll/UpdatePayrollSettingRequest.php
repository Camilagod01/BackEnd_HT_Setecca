<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePayrollSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Sin políticas de roles
    }

    public function rules(): array
    {
        return [
            'workday_hours'      => ['sometimes', 'numeric', 'min:1', 'max:24'],
            'overtime_threshold' => ['sometimes', 'numeric', 'min:1', 'max:24'],
            'base_currency'      => ['sometimes', Rule::in(['CRC', 'USD'])],
            'fx_mode'            => ['sometimes', Rule::in(['none', 'manual', 'daily'])],
            'fx_source'          => ['sometimes', 'string', 'max:50'],
            'rounding_mode'      => ['sometimes', Rule::in(['none', 'half_up', 'down', 'up'])],
        ];
    }

    public function messages(): array
    {
        return [
            'workday_hours.numeric'      => 'workday_hours debe ser numérico.',
            'overtime_threshold.numeric' => 'overtime_threshold debe ser numérico.',
            'base_currency.in'           => 'Moneda inválida.',
            'fx_mode.in'                 => 'Modo de tipo de cambio inválido.',
            'rounding_mode.in'           => 'Modo de redondeo inválido.',
        ];
    }
}
