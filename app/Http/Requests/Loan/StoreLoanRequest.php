<?php

namespace App\Http\Requests\Loan;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool { return true; } // sin policies

    public function rules(): array
    {
        return [
            'employee_id' => ['required','exists:employees,id'],
            'amount'      => ['required','numeric','min:0.01'],
            'currency'    => ['required','in:CRC,USD'],
            'granted_at'  => ['required','date'],
            'status'      => ['nullable','in:active,closed'],
            'notes'       => ['nullable','string'],

            // programación de cuotas
            'schedule.mode'          => ['required','in:next,nth,custom'],
            'schedule.intervalDays'  => ['nullable','integer','min:1'],
            'schedule.firstDueDate'  => ['nullable','date'],
            'schedule.n'             => ['nullable','integer','min:1'],
            'schedule.installments'  => ['nullable','array'],
            'schedule.installments.*.due_date' => ['required_with:schedule.installments','date'],
            'schedule.installments.*.amount'   => ['required_with:schedule.installments','numeric','min:0.01'],
            'schedule.installments.*.remarks'  => ['nullable','string'],
        ];
    }
}

