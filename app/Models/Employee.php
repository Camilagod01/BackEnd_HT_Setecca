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
        'position_id',
        'hire_date',
        'status',
    ];

    protected $casts = [
        'hire_date' => 'date:Y-m-d',
    ];

    protected $appends = [
        'full_name',
    ];

    /** Relación: Empleado pertenece a un Puesto */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /** Accesor conveniente para UI/reportes */
    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }
    
    // Normaliza el código al guardar/actualizar
    protected static function booted(): void
    {
        static::saving(function (self $m) {
            if (isset($m->code) && $m->code !== null) {
                // bajar a minúsculas y asegurar emp-#### si tiene dígitos
                $raw = trim((string)$m->code);
                $digits = preg_replace('/\D+/', '', $raw);
                $m->code = $digits !== ''
                    ? 'emp-' . str_pad($digits, 4, '0', STR_PAD_LEFT)
                    : strtolower($raw);
            }
        });
    }


}

