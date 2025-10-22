<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Holiday;
use Carbon\Carbon;

class GenerateDefaultHolidays extends Command
{
    protected $signature = 'holidays:generate-default {year?}
                            {--reset=0 : Borra los default del año antes de regenerar}';

    protected $description = 'Genera feriados por defecto (nacionales + J/V Santos + domingos) para un año';

    public function handle()
    {
        $year = (int)($this->argument('year') ?? now()->year);
        $reset = (bool)$this->option('reset');

        if ($reset) {
            Holiday::where('origin','default')->whereYear('date', $year)->delete();
        }

        // 1) FERIADOS FIJOS NACIONALES
        $fixed = [
            ['m'=>'01','d'=>'01','name'=>'Año Nuevo'],
            ['m'=>'04','d'=>'11','name'=>'Día de Juan Santamaría'],
            ['m'=>'05','d'=>'01','name'=>'Día Internacional del Trabajo'],
            ['m'=>'07','d'=>'25','name'=>'Anexión del Partido de Nicoya'],
            ['m'=>'08','d'=>'15','name'=>'Día de la Madre y Asunción de la Virgen'],
            ['m'=>'09','d'=>'15','name'=>'Día de la Independencia'],
            ['m'=>'12','d'=>'25','name'=>'Navidad'],
        ];

        foreach ($fixed as $f) {
            $this->upsertDefaultDate("{$year}-{$f['m']}-{$f['d']}", $f['name']);
        }

        // 2) SEMANA SANTA (usando easter_date)
        $easterTs = easter_date($year);
        $thu = date('Y-m-d', strtotime('-3 days', $easterTs)); // Jueves Santo
        $fri = date('Y-m-d', strtotime('-2 days', $easterTs)); // Viernes Santo

        $this->upsertDefaultDate($thu, 'Jueves Santo');
        $this->upsertDefaultDate($fri, 'Viernes Santo');

        $this->info("Feriados por defecto generados para {$year}.");
        return Command::SUCCESS;
    }

    private function upsertDefaultDate(string $ymd, string $name): void
    {
        Holiday::updateOrCreate(
            ['date' => $ymd, 'scope' => 'national', 'origin' => 'default'],
            ['name' => $name, 'paid' => true]
        );
    }
}
