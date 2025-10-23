<?php
namespace App\Services\Fx;

use Illuminate\Support\Facades\Http;

class ExchangerateHostProvider implements FxProviderInterface
{
    public function fetchToday(): array
    {
        $url = config('fx.exhost_url', 'https://api.exchangerate.host/latest?base=USD&symbols=CRC');

        $resp = Http::withoutVerifying()
            ->timeout((int) config('fx.timeout', 15))
            ->get($url);

        if (!$resp->ok()) {
            throw new \RuntimeException('Fallback exchangerate.host sin respuesta HTTP.');
        }

        $data = $resp->json();

        // 🔍 Detección flexible de campos posibles
        $rate = 0;
        if (isset($data['rates']) && is_array($data['rates'])) {
            // Busca cualquier campo que luzca como CRC
            $keys = array_keys($data['rates']);
            $match = null;
            foreach ($keys as $key) {
                if (strtoupper($key) === 'CRC') {
                    $match = $key;
                    break;
                }
            }
            if ($match !== null) {
                $rate = (float) $data['rates'][$match];
            }
        }

        // A veces la API responde como 'result' o 'rate' directamente
        if ($rate <= 0 && isset($data['result'])) {
            $rate = (float) $data['result'];
        }
        if ($rate <= 0 && isset($data['rate'])) {
            $rate = (float) $data['rate'];
        }

        // ✅ Si sigue sin valor, lanza error más claro con ejemplo del JSON recibido
        if ($rate <= 0) {
            throw new \RuntimeException(
                'Fallback exchangerate.host sin tasa válida. Estructura recibida: ' . json_encode(array_keys($data))
            );
        }

        $date = $data['date'] ?? now()->toDateString();

        return [
            'rate'   => $rate,
            'source' => 'exchangerate_host',
            'date'   => $date,
        ];
    }
}
