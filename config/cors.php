<?php

return [
    'paths' => ['api/*', 'docs/*'],
    'allowed_methods' => ['*'],
    // Séparez plusieurs origines par des virgules : CORS_ALLOWED_ORIGINS=http://localhost:5173,https://app.exemple.cm
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://localhost:3000'))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-Request-Id', 'Retry-After'],
    'max_age' => 3600,
    'supports_credentials' => false, // authentification par jeton Bearer, pas par cookie
];
