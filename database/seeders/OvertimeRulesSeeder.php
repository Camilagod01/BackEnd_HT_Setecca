<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OvertimeRulesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\OvertimeRule::updateOrCreate(
        ['rule_type' => 'daily', 'condition' => '>=8h'],
        ['multiplier' => 1.50, 'active' => true]
    );

    \App\Models\OvertimeRule::updateOrCreate(
        ['rule_type' => 'weekend', 'condition' => 'sunday'],
        ['multiplier' => 2.00, 'active' => true]
    );
    }
}
