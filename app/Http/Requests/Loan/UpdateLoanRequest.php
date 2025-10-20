<?php

namespace App\Http\Requests\Loan;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLoanRequest extends FormRequest
{
    public function authorize(): bool { return true; } // sin policies

    public function rules(): array
    {
        return [
            'amount'     => ['sometimes','numeric','min:0.01'],
            'currency'   => ['sometimes','in:CRC,USD'],
            'granted_at' => ['sometimes','date'],
            'status'     => ['sometimes','in:active,closed'],
            'notes'      => ['nullable','string'],
        ];
    }
}
