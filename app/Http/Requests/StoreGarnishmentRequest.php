<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGarnishmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ajusta si usas policies
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required','integer','exists:employees,id'],
            'order_no'    => ['nullable','string','max:50'],

            // columnas reales: mode + value
            'mode'        => ['required','in:percent,amount'],
            'value'       => ['required','numeric','min:0'],

            'start_date'  => ['required','date'],
            'end_date'    => ['nullable','date','after_or_equal:start_date'],

            'priority'    => ['nullable','integer','min:1','max:5'],
            'active'      => ['nullable','boolean'],

            'notes'       => ['nullable','string','max:1000'],
        ];
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated();

        // defaults razonables
        $data['priority'] = $data['priority'] ?? 1;
        $data['active']   = array_key_exists('active',$data) ? (bool)$data['active'] : true;

        return $data;
    }
}
