<?php
namespace App\Services\Fx;

use Illuminate\Support\Facades\Http;

class BccrProvider implements FxProviderInterface
{
    public function fetchToday(): array
    {
        $attempts = 0;
        $date = now()->timezone(config('app.timezone', 'America/Costa_Rica'));

        do {
            $res = $this->fetchForDate($date->format('Y-m-d'));
            if ($res !== null) {
                return $res;
            }
            $date = $date->subDay();
            $attempts++;
        } while ($attempts < 7);

        throw new \RuntimeException('BCCR sin datos recientes (7 días).');
    }

    private function fetchForDate(string $ymd): ?array
    {
        $cfg   = config('fx.bccr');
        $url   = $cfg['url'];
        $ind   = (int) $cfg['indicator'];    // 318 venta, 317 compra
        $name  = $cfg['name'];
        $subs  = $cfg['subniveles'];

        $dmy = date('d/m/Y', strtotime($ymd));
        $query = [
            'Indicador'   => $ind,
            'FechaInicio' => $dmy,
            'FechaFinal'  => $dmy,
            'Nombre'      => $name,
            'SubNiveles'  => $subs,
        ];

        $resp = Http::withoutVerifying()
            ->timeout((int) config('fx.timeout', 12))
            ->asString()
            ->get($url, $query);

        if (!$resp->ok()) {
            return null;
        }

        $body = $resp->body();
        if (!$body) return null;

        // Manejo de namespaces; simplexml a veces necesita limpieza
        $xml = @simplexml_load_string($body);
        if ($xml === false) return null;

        // Busca valores en varias rutas comunes
        $candidates = [
            '//INGC011_CAT_INDICADORECONOMIC/NUM_VALOR',
            '//Datos_de_INGC011_CAT_INDICADORECONOMIC/INGC011_CAT_INDICADORECONOMIC/NUM_VALOR',
            '//*/NUM_VALOR',
            '//*/Valor',
        ];

        $raw = null;
        foreach ($candidates as $xp) {
            $nodes = $xml->xpath($xp);
            if ($nodes && isset($nodes[0])) {
                $raw = (string) $nodes[0];
                break;
            }
        }
        if ($raw === null) return null;

        // Normaliza "506,75" -> "506.75", quita separadores de miles si vienen
        $norm = str_replace(['.', ','], ['', '.'], preg_replace('/[^\d,\.]/', '', $raw));
        $rate = (float) $norm;
        if ($rate <= 0) return null;

        // Determina fecha del dato si viene
        $dateYmd = $ymd;
        $dateCandidates = [
            '//INGC011_CAT_INDICADORECONOMIC/DES_FECHA',
            '//*/DES_FECHA',
        ];
        foreach ($dateCandidates as $dx) {
            $dNodes = $xml->xpath($dx);
            if ($dNodes && isset($dNodes[0])) {
                $d = \DateTime::createFromFormat('d/m/Y', (string) $dNodes[0]);
                if ($d) { $dateYmd = $d->format('Y-m-d'); break; }
            }
        }

        return [
            'rate'   => $rate,
            'source' => 'bccr',
            'date'   => $dateYmd,
        ];
    }
}
