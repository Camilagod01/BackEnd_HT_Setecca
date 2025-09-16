<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MetricsHoursRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            'from'        => 'required|date',
            'to'          => 'required|date|after_or_equal:from',
        ];
    }
}
