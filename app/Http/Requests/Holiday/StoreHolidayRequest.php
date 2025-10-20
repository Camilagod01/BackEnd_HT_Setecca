<?php

namespace App\Http\Requests\Holiday;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'  => ['required', 'date', 'unique:holidays,date'],
            'name'  => ['required', 'string', 'max:120'],
            'scope' => ['required', Rule::in(['national','company'])],
            'paid'  => ['sometimes', 'boolean'], // Si lo necesita la tabla
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'La fecha es requerida.',
            'date.unique'   => 'Ya existe un feriado en esa fecha.',
            'name.required' => 'El nombre es requerido.',
            'scope.in'      => 'Ámbito inválido.',
        ];
    }
}
