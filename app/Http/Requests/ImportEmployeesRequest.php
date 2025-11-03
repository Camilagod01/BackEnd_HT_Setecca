<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportEmployeesRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
      return [
        'file' => ['required','file','mimes:csv,txt','max:5120'],
        'dry_run' => ['required','boolean'],                // espera true/false
        'delimiter' => ['nullable', Rule::in([',',';'])],
      ];
    }

    protected function prepareForValidation()
    {
      if ($this->has('dry_run')) {
        $val = $this->input('dry_run');
        // normaliza "1"/"0" o "true"/"false"
        if (is_string($val)) {
          $val = strtolower($val);
          $this->merge(['dry_run' => in_array($val, ['1','true'], true)]);
        }
      }
    }
}
