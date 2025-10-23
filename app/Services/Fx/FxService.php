<?php
namespace App\Services\Fx;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\DB;

class FxService
{
    public function __construct(protected FxProviderInterface $provider) {}

    /**
     * Consulta el tipo de cambio (auto/manual), guarda y retorna datos básicos.
     */
    public function updateRate(): array
    {
        $settings = DB::table('payroll_settings')->where('id', 1)->first();

        $mode   = $settings->fx_mode ?? 'auto';
        $source = $settings->fx_source ?? 'bccr';
        $base   = config('fx.base', 'CRC');
        $quote  = config('fx.quote', 'USD');

        if ($mode === 'manual') {
            $rate = (float) ($settings->fx_manual_rate ?? 0);
            if ($rate <= 0) {
                throw new \RuntimeException('fx_manual_rate inválido en payroll_settings.');
            }

            $payload = [
                'rate_date'      => now()->toDateString(),
                'base_currency'  => $base,
                'quote_currency' => $quote,
                'rate'           => $rate,
                'source'         => 'manual',
            ];
        } else {
            // modo auto
            $fetched = $this->provider->fetchToday();

            $payload = [
                'rate_date'      => $fetched['date'],
                'base_currency'  => $base,
                'quote_currency' => $quote,
                'rate'           => $fetched['rate'],
                'source'         => $fetched['source'],
            ];
        }

        // Guardar/upsert en exchange_rates
        $row = ExchangeRate::updateOrCreate(
            [
                'rate_date'      => $payload['rate_date'],
                'base_currency'  => $payload['base_currency'],
                'quote_currency' => $payload['quote_currency'],
            ],
            [
                'rate'   => $payload['rate'],
                'source' => $payload['source'],
            ]
        );

        // Devolver información resumida
        return [
            'date'   => $row->rate_date->toDateString(),
            'rate'   => (float) $row->rate,
            'source' => $row->source,
            'base'   => $row->base_currency,
            'quote'  => $row->quote_currency,
            'mode'   => $mode,
        ];
    }

    /**
     * Obtiene el último tipo de cambio disponible.
     */
    public function latest(): ?ExchangeRate
    {
        $base  = config('fx.base', 'CRC');
        $quote = config('fx.quote', 'USD');

        return ExchangeRate::where('base_currency', $base)
            ->where('quote_currency', $quote)
            ->orderByDesc('rate_date')
            ->orderByDesc('id')
            ->first();
    }
}
