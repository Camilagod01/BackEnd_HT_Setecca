<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollSetting extends Model
{
    use HasFactory;

    protected $table = 'payroll_settings';

    // Si lo necesita la tabla
    protected $fillable = [
        'workday_hours',      // Horas laborales por día (ej. 8.00)
        'overtime_threshold', // Umbral para horas extra por día (ej. 8.00)
        'base_currency',      // 'CRC' | 'USD'
        'fx_mode',            // 'none' | 'manual' | 'daily'
        'fx_source',          // Fuente del tipo de cambio (ej. 'BCCR')
        'fx_manual_rate',
        'rounding_mode',      // 'none' | 'half_up' | 'down' | 'up'
    ];

   protected $casts = [
        'workday_hours'      => 'integer',
        'overtime_threshold' => 'integer',
        'fx_manual_rate'     => 'decimal:4',
    ];


    /**
     * Devuelve (o crea) el único registro de configuración.
     * Útil para tener una pantalla de edición única.
     */
    /*public static function singleton(array $defaults = []): self
    {
        $row = static::query()->first();
        if (!$row) {
            $row = static::create(array_merge([
                'workday_hours'      => 8.00,
                'overtime_threshold' => 8.00,
                'base_currency'      => 'CRC',
                'fx_mode'            => 'none',
                'fx_source'          => 'manual',
                'rounding_mode'      => 'none',
            ], $defaults));
        }
        return $row;
    }
}
*/


//Agregado en sprint 8
//Para evitar sorpresas, agrega casts/constantes en el modelo
 public const FX_MODE_MANUAL = 'manual';
    public const FX_MODE_AUTO   = 'auto';

    public const FX_SOURCE_MANUAL = 'manual';
    public const FX_SOURCE_BCCR   = 'BCCR';
}