<?php
return [
    'default_mode'   => env('FX_DEFAULT_MODE', 'auto'),
    'default_source' => env('FX_PROVIDER', 'bccr'),
    'timeout'        => (int) env('FX_HTTP_TIMEOUT', 12),

    'base'  => env('FX_BASE', 'CRC'),
    'quote' => env('FX_QUOTE', 'USD'),

    // BCCR
    'bccr' => [
        'url'        => env('FX_BCCR_URL'),
        'indicator'  => (int) env('FX_BCCR_INDICATOR', 318), // 318 venta, 317 compra
        'name'       => env('FX_BCCR_NAME', 'HTSETECCA'),
        'subniveles' => env('FX_BCCR_SUBNIVELES', 'N'),
    ],

    // Fallback (opcional)
    'exhost_url' => env('FX_EXHOST_URL', 'https://api.exchangerate.host/latest?base=USD&symbols=CRC'),

    'gometa_url' => env('FX_GOMETA_URL', 'https://apis.gometa.org/tdc/tdc.json'),
];
