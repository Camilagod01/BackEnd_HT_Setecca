<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'first_name',
        'last_name',
        'email',
        'position',
        'hire_date',
        'status',
    ];
    
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

