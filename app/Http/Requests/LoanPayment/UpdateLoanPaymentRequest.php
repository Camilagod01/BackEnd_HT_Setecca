<?php

namespace App\Http\Requests\LoanPayment;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLoanPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // sin policies
    }

    public function rules(): array
    {
        return [
            // Cambios de estado
            'status'  => ['sometimes','in:pending,paid,skipped'],

            // Reprogramación
            'due_date' => ['sometimes','date'],

            // Edición de monto (si se quiere ajustar la cuota)
            'amount'   => ['sometimes','numeric','min:0.01'],

            // Fuente/nota administrativa
            'source'   => ['sometimes','in:payroll,manual'],
            'remarks'  => ['nullable','string'],

            // Operación “shortcut” opcional (te permitirá endpoints más simples):
            // 'action' admite: 'mark_paid', 'mark_skipped', 'reschedule'
            'action'   => ['nullable','in:mark_paid,mark_skipped,reschedule'],
        ];
    }
}
