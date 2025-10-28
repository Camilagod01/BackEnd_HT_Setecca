<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateDefaultHolidays extends Command
{
    protected $signature = 'holidays:generate-default {year?}
                            {--reset=0 : Borra los default del año antes de regenerar}';

    // Mejor descripción: solo feriados nacionales + J/V Santos
    protected $description = 'Genera feriados por defecto (nacionales + Jueves/Viernes Santos) para un año';

    public function handle()
    {
        $year  = (int)($this->argument('year') ?? now()->year);
        $reset = (bool)$this->option('reset');

        $count = app(\App\Services\HolidayGenerator::class)
                    ->generateDefault($year, $reset);

        $this->info("Feriados por defecto generados/asegurados para {$year} (nuevos: {$count}).");
        return self::SUCCESS;
    }
}
