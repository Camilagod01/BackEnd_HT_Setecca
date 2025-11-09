<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;



class Position extends Model
{

    use HasFactory;
    protected $fillable = [
        'code',
        'name',
        'base_hourly_rate',
        'currency',
        'salary_type',
        'default_salary_amount',
        'default_salary_currency',
        'is_active',
    ];

    protected $casts = [
        'base_hourly_rate' => 'float',
        'default_salary_amount' => 'float',
        'is_active' => 'boolean',
    ];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'position_id');
    }

    public function defaultComp(): array
    {
        $salaryType = $this->salary_type ?? 'monthly';
        $amount = $this->default_salary_amount;

        if ($amount === null && $this->base_hourly_rate !== null) {
            $salaryType = 'hourly';
            $amount = (float) $this->base_hourly_rate;
        }

        $currency = $this->default_salary_currency ?? $this->currency ?? 'CRC';

        return [
            'salary_type' => $salaryType,
            'salary_amount' => (float) $amount,
            'salary_currency' => $currency,
        ];
    }
}
