<?php

$frontendUrls = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('FRONTEND_URLS', 'http://localhost:5173,http://127.0.0.1:5173')),
)));

return [
    'paths' => ['api/*', 'login', 'logout'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $frontendUrls,

    // Vite may hop to 5174+ when 5173 is taken; allow any local HTTP(S) origin in local env.
    'allowed_origins_patterns' => env('APP_ENV', 'production') === 'local'
        ? ['#\Ahttps?://(localhost|127\.0\.0\.1)(:\d+)?\z#']
        : [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
