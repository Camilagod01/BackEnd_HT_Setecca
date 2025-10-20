<?php

namespace App\Http\Requests\Advance;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdvanceRequest extends FormRequest
{
    public function authorize(): bool { return true; } // sin policies

    public function rules(): array
    {
        return [
            'employee_id'     => ['required','exists:employees,id'],
            'amount'          => ['required','numeric','min:0.01'],
            'currency'        => ['required','in:CRC,USD'],
            'granted_at'      => ['required','date'],
            'notes'           => ['nullable','string'],
            'status'          => ['nullable','in:pending,applied,cancelled'],
            'scheduling_json' => ['nullable','array'],
        ];
    }
}
