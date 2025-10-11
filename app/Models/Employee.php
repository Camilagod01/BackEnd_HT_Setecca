<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'first_name',
        'last_name',
        'email',
        'hire_date',
        'status',
        'position_id',

        // Nuevos campos para herencia/override de salario
        'use_position_salary',
        'salary_type',              // 'monthly' | 'hourly'
        'salary_override_amount',
        'salary_override_currency', // 'CRC' | 'USD'
    ];

    protected $casts = [
        'hire_date' => 'date:Y-m-d',
        'use_position_salary'     => 'boolean',
        'salary_override_amount'  => 'decimal:2',
    ];

    protected $appends = [
        'full_name',
        // Opcional: 'effective_comp', // si quieres exponer el salario efectivo directo en JSON
    ];

    /** Relación: Empleado pertenece a un Puesto (positions.id) */
    public function position(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Position::class, 'position_id');
    }

    public function sickLeaves()
    {
        return $this->hasMany(SickLeave::class, 'employee_id');
    }

    /** Accesor conveniente para UI/reportes */
    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    /**
     * (Opcional) Accesor de “salario efectivo” para respuestas JSON.
     * Requiere \App\Services\EmployeeCompService registrado.
     */
    public function getEffectiveCompAttribute(): ?array
    {
        try {
            return app(\App\Services\EmployeeCompService::class)->effectiveComp($this);
        } catch (\Throwable $e) {
            return null; // si no tiene puesto ni override aún
        }
    }

    /** Normaliza el código al guardar/actualizar */
    protected static function booted(): void
    {
        static::saving(function (self $m) {
            if (isset($m->code) && $m->code !== null) {
                $raw = trim((string)$m->code);
                $digits = preg_replace('/\D+/', '', $raw);
                $m->code = $digits !== ''
                    ? 'emp-' . str_pad($digits, 4, '0', STR_PAD_LEFT)
                    : strtolower($raw);
            }
        });
    }
}
