<?php
namespace App\Services\Fx;

/**
 * Contrato para proveedores de tipo de cambio.
 * Cada proveedor (BCCR, exchangerate.host, etc.) debe implementar fetchToday().
 */
interface FxProviderInterface
{
    /**
     * Retorna un arreglo con:
     *  - rate: número decimal (CRC por USD)
     *  - source: nombre del proveedor (bccr, exchangerate_host, manual)
     *  - date: fecha Y-m-d del tipo de cambio
     */
    public function fetchToday(): array;
}
