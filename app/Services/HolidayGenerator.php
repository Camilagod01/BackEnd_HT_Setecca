<?php

namespace App\Services;

use App\Models\Holiday;

class HolidayGenerator
{
    public function generateDefault(int $year, bool $reset = false): int
    {
        if ($reset) {
            Holiday::where('origin','default')->whereYear('date', $year)->delete();
        }

        // Fijos nacionales
        $fixed = [
            ['m'=>'01','d'=>'01','name'=>'Año Nuevo'],
            ['m'=>'04','d'=>'11','name'=>'Día de Juan Santamaría'],
            ['m'=>'05','d'=>'01','name'=>'Día Internacional del Trabajo'],
            ['m'=>'07','d'=>'25','name'=>'Anexión del Partido de Nicoya'],
            ['m'=>'08','d'=>'15','name'=>'Día de la Madre y Asunción de la Virgen'],
            ['m'=>'09','d'=>'15','name'=>'Día de la Independencia'],
            ['m'=>'12','d'=>'25','name'=>'Navidad'],
        ];

        $count = 0;
        foreach ($fixed as $f) {
            $count += $this->upsertDefaultDate("{$year}-{$f['m']}-{$f['d']}", $f['name']);
        }

        // Semana Santa (Jueves/Viernes)
        $easterTs = easter_date($year);
        $thu = date('Y-m-d', strtotime('-3 days', $easterTs)); // Jueves Santo
        $fri = date('Y-m-d', strtotime('-2 days', $easterTs)); // Viernes Santo
        $count += $this->upsertDefaultDate($thu, 'Jueves Santo');
        $count += $this->upsertDefaultDate($fri, 'Viernes Santo');

        return $count;
    }

    private function upsertDefaultDate(string $ymd, string $name): int
    {
        $before = Holiday::where(['date'=>$ymd,'scope'=>'national','origin'=>'default'])->exists();

        Holiday::updateOrCreate(
            ['date' => $ymd, 'scope' => 'national', 'origin' => 'default'],
            ['name' => $name, 'paid' => true]
        );

        return $before ? 0 : 1;
    }
}
