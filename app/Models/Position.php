<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    // Si usas guarded/ fillable, elige uno (recomendado fillable)
    protected $fillable = [
        'code',           // p.ej. GER, TEC, ADM
        'name',           // Gerente, Técnico, etc.
        'base_hourly_rate',
        'currency',       // CRC / USD (por ahora fijo aquí)
    ];

    protected $casts = [
        'base_hourly_rate' => 'float',
    ];

    /** Empleados que tienen este puesto */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /** Búsqueda rápida por código */
    public function scopeCode($q, string $code)
    {
        return $q->where('code', $code);
    }

    public function position()
{
    return $this->belongsTo(\App\Models\Position::class);
}
}
