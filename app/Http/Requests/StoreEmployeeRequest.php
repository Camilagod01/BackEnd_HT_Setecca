<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'code'        => ['required','string','max:50','unique:employees,code'],
            'first_name'  => ['required','string','max:100'],
            'last_name'   => ['required','string','max:100'],
            'email'       => ['nullable','email','max:150','unique:employees,email'],
            'status'      => ['required','in:active,inactive,suspended'],
            'position_id' => ['nullable','exists:positions,id'],
            'hire_date'   => ['nullable','date'],
            'salary_currency' => ['nullable','string','size:3'],
            'salary_amount'   => ['nullable','numeric','min:0'],
            'garnish_cap_rate'=> ['nullable','numeric','min:0','max:1'],
        ];
    }
}
