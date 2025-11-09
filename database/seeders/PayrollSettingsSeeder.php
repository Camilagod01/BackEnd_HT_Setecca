<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PayrollSetting;

class PayrollSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
$settings = PayrollSetting::first();

        if (!$settings) {
            PayrollSetting::create([
                'workday_hours'      => 8,
                'overtime_threshold' => 8,
                'base_currency'      => 'CRC',
                'fx_mode'            => 'auto',   // 'manual' | 'auto'
                'fx_source'          => 'BCCR',   // 'manual' | 'BCCR'
                'fx_manual_rate'     => null,
                'rounding_mode'      => 'none',
            ]);
            return;
        }

        // Idempotente: ajustar sin romper instalaciones previas
        $settings->workday_hours      = $settings->workday_hours ?? 8;
        $settings->overtime_threshold = $settings->overtime_threshold ?? 8;
        $settings->base_currency      = $settings->base_currency ?? 'CRC';

        // Normalizamos valores inválidos heredados
        if (!in_array($settings->fx_mode, ['manual', 'auto'], true)) {
            $settings->fx_mode = 'manual';
        }
        if (!in_array($settings->fx_source, ['manual', 'BCCR'], true)) {
            $settings->fx_source = 'manual';
        }

        // Si está en manual pero no hay tasa, dale un valor neutro (o null si tu lógica lo permite)
        if ($settings->fx_mode === 'manual' && is_null($settings->fx_manual_rate)) {
            $settings->fx_manual_rate = 1.0000;
        }

        if (empty($settings->rounding_mode)) {
            $settings->rounding_mode = 'none';
        }

        $settings->save();
    }
}