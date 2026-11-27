<?php

declare(strict_types=1);

/*
| CORS cerrado por defecto: el frontend y la API comparten origen detrás de
| Nginx, así que no se necesita. Solo para desarrollo con otro origen,
| define CORS_ALLOWED_ORIGINS=http://localhost:4200 (separado por comas).
*/

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Requested-With', 'X-Request-Id', 'If-None-Match'],
    'exposed_headers' => ['X-Request-Id', 'Content-Disposition', 'ETag'],
    'max_age' => 600,
    'supports_credentials' => true,
];
