<?php

namespace App\Http\Requests\Absence;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Absence;

class UpdateAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Cuando actualizas a full_day, horas pasa a null; si es hours, normaliza
        $kind  = $this->input('kind', $this->route('absence')->kind ?? null);
        $hours = $this->input('hours');

        if ($kind === 'full_day') {
            $this->merge(['hours' => null]);
        } elseif ($kind === 'hours' && $hours !== null && is_numeric($hours)) {
            $this->merge(['hours' => number_format((float)$hours, 2, '.', '')]);
        }
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['sometimes', 'integer', 'exists:employees,id'],
            'start_date'  => ['sometimes', 'date'],
            'end_date'    => ['sometimes', 'date', 'after_or_equal:start_date'],
            'kind'        => ['sometimes', Rule::in(['full_day','hours'])],
            'hours'       => [
                /*// Requerido si (kind enviado = 'hours') o si el registro actual es 'hours' y no se envía kind
                Rule::requiredIf(function () {
                    $sentKind    = $this->input('kind');
                    $currentKind = optional($this->route('absence'))->kind;
                    return ($sentKind === 'hours') || ($sentKind === null && $currentKind === 'hours');
                }),*/
                'nullable', 'numeric', 'min:0.25', 'max:12'
            ],
            'reason'      => ['sometimes', 'nullable', 'string', 'max:150'],
            'status'      => ['sometimes', Rule::in(['pending','approved','rejected'])],
            'notes'       => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $absence  = $this->route('absence'); // route-model binding
            $ignoreId = is_object($absence) ? $absence->id : null;

            $empId = (int) ($this->input('employee_id') ?? ($absence->employee_id ?? 0));
            $start = $this->input('start_date') ?? ($absence->start_date?->format('Y-m-d') ?? null);
            $end   = $this->input('end_date')   ?? ($absence->end_date?->format('Y-m-d')   ?? null);

            if ($empId && $start && $end) {
                $overlap = Absence::overlapping($empId, $start, $end, $ignoreId)->exists();
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
            'end_date.after_or_equal' => 'La fecha fin debe ser posterior o igual al inicio.',
            'kind.in'                 => 'Tipo de permiso inválido.',
           // 'hours.required'          => 'Las horas son requeridas cuando el tipo es por horas.',
            'hours.min'               => 'Las horas deben ser al menos 0.25.',
            'hours.max'               => 'Las horas no pueden exceder 12.',
            'status.in'               => 'Estado inválido.',
        ];
    }
}
