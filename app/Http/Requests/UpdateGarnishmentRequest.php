<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGarnishmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ajusta si usas policies
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['sometimes','integer','exists:employees,id'],
            'order_no'    => ['sometimes','nullable','string','max:50'],

            'mode'        => ['sometimes','in:percent,amount'],
            'value'       => ['sometimes','numeric','min:0'],

            'start_date'  => ['sometimes','date'],
            'end_date'    => ['sometimes','nullable','date','after_or_equal:start_date'],

            'priority'    => ['sometimes','integer','min:1','max:5'],
            'active'      => ['sometimes','boolean'],

            'notes'       => ['sometimes','nullable','string','max:1000'],
        ];
    }
}
