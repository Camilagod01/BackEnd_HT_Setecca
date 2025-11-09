<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayrollPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required','integer','exists:employees,id'],
            'from'        => ['required','date','before_or_equal:to'],
            'to'          => ['required','date','after_or_equal:from'],
        ];
    }
}
