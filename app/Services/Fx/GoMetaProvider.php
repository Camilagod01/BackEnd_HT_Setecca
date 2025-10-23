<?php
namespace App\Services\Fx;

use Illuminate\Support\Facades\Http;

class GoMetaProvider implements FxProviderInterface
{
    public function fetchToday(): array
    {
        $url = config('fx.gometa_url', 'https://apis.gometa.org/tdc/tdc.json');

        $resp = Http::withoutVerifying()
            ->timeout((int) config('fx.timeout', 15))
            ->get($url);

        if (!$resp->ok()) {
            throw new \RuntimeException('GoMeta TDC sin respuesta HTTP.');
        }

        $data = $resp->json();

        // El JSON típico trae "venta" (CRC por USD)
        $rate = 0;
        if (isset($data['venta'])) {
            $rate = (float) $data['venta'];
        } elseif (isset($data['sale'])) {
            $rate = (float) $data['sale'];
        }

        if ($rate <= 0) {
            throw new \RuntimeException('GoMeta TDC sin tasa válida.');
        }

        // Fecha si viene, si no, hoy
        $date = $data['fecha'] ?? $data['date'] ?? now()->toDateString();

        return [
            'rate'   => $rate,
            'source' => 'gometa_tdc',
            'date'   => is_string($date) ? substr($date, 0, 10) : now()->toDateString(),
        ];
    }
}
