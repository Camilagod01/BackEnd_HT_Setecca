<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PayrollSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\PayrollSetting::query()->updateOrCreate(
        ['id' => 1],
        [
            'workday_hours'      => 8,
            'overtime_threshold' => 8,
            'base_currency'      => 'CRC',
            'fx_mode'            => 'manual',
            'fx_source'          => 'BCCR',
            'rounding_mode'      => 'half_up',
        ]
    );
    }
}
