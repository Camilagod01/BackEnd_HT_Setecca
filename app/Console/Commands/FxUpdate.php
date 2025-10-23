<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Fx\FxService;

class FxUpdate extends Command
{
    protected $signature = 'fx:update';
    protected $description = 'Actualiza el tipo de cambio (auto/manual) en exchange_rates.';

    public function handle(FxService $fx)
    {
        try {
            $res = $fx->updateRate();
            $this->info("FX actualizado: {$res['base']}/{$res['quote']} ₡{$res['rate']} {$res['date']} (source: {$res['source']}, mode: {$res['mode']})");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error FX: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
