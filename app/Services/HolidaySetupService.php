<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HolidaySetupService
{
    /**
     * Asegura feriados del año actual y anterior.
     * Si es diciembre, también prepara el siguiente año (opcional).
     */
    public function ensure(): void
    {
        $this->ensureYear((int) now()->year);          // presente
        $this->ensureYear((int) now()->subYear()->year); // anterior

        // Opcional: prepara el año siguiente en diciembre
        if ((int) now()->format('m') >= 12) {
            $this->ensureYear((int) now()->addYear()->year);
        }
    }

    public function ensureYear(int $year): void
    {
        $col = $this->dateColumn();
        if (!$col) return;

        $from = "$year-01-01";
        $to   = "$year-12-31";

        $exists = DB::table('holidays')
            ->whereDate($col, '>=', $from)
            ->whereDate($col, '<=', $to)
            ->exists();

        if (!$exists) {
            app(\App\Services\HolidayGenerator::class)->generateDefault($year, false);
        }
    }


    private function dateColumn(): ?string
    {
        if (!Schema::hasTable('holidays')) return null;
        foreach (['date','holiday_date','observed_date'] as $c) {
            if (Schema::hasColumn('holidays', $c)) return $c;
        }
        return null;
    }
}
