<?php

namespace App\Http\Requests\Holiday;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('holiday')?->id ?? $this->route('id');

        return [
            'date'  => ['sometimes', 'date', 'unique:holidays,date,' . $id],
            'name'  => ['sometimes', 'string', 'max:120'],
            'scope' => ['sometimes', Rule::in(['national','company'])],
            'paid'  => ['sometimes', 'boolean'], // Si lo necesita la tabla
        ];
    }

    public function messages(): array
    {
        return [
            'date.unique' => 'Ya existe un feriado en esa fecha.',
            'scope.in'    => 'Ámbito inválido.',
        ];
    }
}
