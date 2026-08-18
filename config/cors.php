<?php

return [
    'paths' => ['api/*', 'health', '/'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ORIGINS', '*'))
    ))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-Correlation-Id', 'Retry-After'],
    'max_age' => 0,
    'supports_credentials' => env('CORS_ORIGINS', '*') !== '*' && env('CORS_ORIGINS', '*') !== '',
];
