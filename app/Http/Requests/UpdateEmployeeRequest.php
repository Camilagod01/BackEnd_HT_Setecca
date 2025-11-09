<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $id = $this->route('id'); // según tus rutas /employees/{id}

        return [
            'code'        => ['sometimes','string','max:50', Rule::unique('employees','code')->ignore($id)],
            'first_name'  => ['sometimes','string','max:100'],
            'last_name'   => ['sometimes','string','max:100'],
            'email'       => ['sometimes','nullable','email','max:150', Rule::unique('employees','email')->ignore($id)],
            'status'      => ['sometimes','in:active,inactive,suspended'],
            'position_id' => ['sometimes','nullable','exists:positions,id'],
            'hire_date'   => ['sometimes','nullable','date'],
            'salary_currency' => ['sometimes','nullable','string','size:3'],
            'salary_amount'   => ['sometimes','nullable','numeric','min:0'],
            'garnish_cap_rate'=> ['sometimes','nullable','numeric','min:0','max:1'],
        ];
    }
}
