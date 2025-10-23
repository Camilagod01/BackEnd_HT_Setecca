<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Fx\FxProviderInterface;
use App\Services\Fx\BccrProvider;
use App\Services\Fx\ExchangerateHostProvider;
use App\Services\Fx\GoMetaProvider;
use App\Services\Fx\FxService;

class FxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FxProviderInterface::class, function ($app) {
            $primary = config('fx.default_source', 'bccr');

            $chain = [];

            // Principal (según ENV)
            $chain[] = match ($primary) {
                'bccr'              => new BccrProvider(),
                'exchangerate_host' => new ExchangerateHostProvider(),
                'gometa_tdc'        => new GoMetaProvider(),
                default             => new BccrProvider(),
            };

            // Fallback 1: exchangerate.host
            $chain[] = new ExchangerateHostProvider();
            // Fallback 2: GoMeta
            $chain[] = new GoMetaProvider();

            // Proxy que intenta en cadena hasta que uno funcione
            return new class($chain) implements FxProviderInterface {
                public function __construct(private array $providers) {}
                public function fetchToday(): array {
                    $errors = [];
                    foreach ($this->providers as $p) {
                        try {
                            return $p->fetchToday();
                        } catch (\Throwable $e) {
                            $errors[] = get_class($p).': '.$e->getMessage();
                        }
                    }
                    throw new \RuntimeException('Todos los proveedores fallaron. Detalles: '.implode(' | ', $errors));
                }
            };
        });

        $this->app->singleton(FxService::class, function ($app) {
            return new FxService($app->make(FxProviderInterface::class));
        });
    }
}
