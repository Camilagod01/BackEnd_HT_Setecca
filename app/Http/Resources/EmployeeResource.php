<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'              => $this->id,
            'code'            => $this->code,
            'first_name'      => $this->first_name,
            'last_name'       => $this->last_name,
            'full_name'       => trim(($this->first_name ?? '').' '.($this->last_name ?? '')),
            'email'           => $this->email,
            'position_id'     => $this->position_id,
            'position_name'   => optional($this->position)->name,
            'status'          => $this->status,
            'garnish_cap_rate' => is_null($this->garnish_cap_rate) ? null : (float) $this->garnish_cap_rate, // <- Añadido
            'hire_date'       => $this->hire_date,
            'salary_currency' => $this->salary_currency,
            'salary_amount'   => $this->salary_amount,
            'created_at'      => optional($this->created_at)?->toDateTimeString(),
            'updated_at'      => optional($this->updated_at)?->toDateTimeString(),
        ];
    }
}
