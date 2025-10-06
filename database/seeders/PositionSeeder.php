<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Position;

class PositionSeeder extends Seeder
{
    
    public function run(): void
    {
        $positions = [
            [
                'code' => 'GER',
                'name' => 'Gerente',
                'base_hourly_rate' => 4500, // ₡ por hora
                'currency' => 'CRC',
            ],
            [
                'code' => 'TEC',
                'name' => 'Técnico',
                'base_hourly_rate' => 3000,
                'currency' => 'CRC',
            ],
            [
                'code' => 'ADM',
                'name' => 'Asistente',
                'base_hourly_rate' => 2300,
                'currency' => 'CRC',
            ],
        ];

        foreach ($positions as $pos) {
            Position::updateOrCreate(
                ['code' => $pos['code']], // evita duplicados
                $pos
            );
        }
    }
}
