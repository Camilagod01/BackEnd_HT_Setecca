<?php

namespace App\Http\Requests\Advance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdvanceRequest extends FormRequest
{
    public function authorize(): bool { return true; } // sin policies

    public function rules(): array
    {
        return [
            'amount'          => ['sometimes','numeric','min:0.01'],
            'currency'        => ['sometimes','in:CRC,USD'],
            'granted_at'      => ['sometimes','date'],
            'notes'           => ['nullable','string'],
            'status'          => ['sometimes','in:pending,applied,cancelled'],
            'scheduling_json' => ['nullable','array'],
        ];
    }
}
