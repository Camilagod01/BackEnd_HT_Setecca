<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    // Columnas reales de tu tabla
    protected $fillable = [
        'rate_date',
        'base_currency',
        'quote_currency',
        'rate',
        'source',
    ];

    protected $casts = [
        'rate_date' => 'date',
        'rate'      => 'decimal:6',
    ];
}
