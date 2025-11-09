<?php

namespace App\Services\Payroll;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FxService
{
    /**
     * Devuelve la tasa CRC por 1 USD para la fecha dada.
     * Si no hay tasa exacta ese día, usa la última tasa <= fecha.
     * Si no existe ningún registro, retorna 1.0 como fallback.
     */
    public function usdToCrcRateForDate(Carbon|string $date): float
    {
        $d = $date instanceof Carbon ? $date->toDateString() : (string)$date;

        $row = DB::table('exchange_rates')
            ->where('currency', 'USD')
            ->where('date', '<=', $d)
            ->orderByDesc('date')
            ->first();

        return $row ? (float)$row->rate_to_crc : 1.0;
    }
}
