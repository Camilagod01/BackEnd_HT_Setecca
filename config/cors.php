<?php

return [
    'paths' => ['api/*'],  //,'sanctum/csrf-cookie'
    'allowed_methods' => ['*'],

    // Permite el front de Vite y el front servido por Apache
    'allowed_origins' => [
        'http://localhost:5173',   // Vite dev server
        'http://localhost:8080',   // Front bajo Apache/Laragon
    ],

    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
