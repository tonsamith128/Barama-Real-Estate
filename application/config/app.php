<?php

declare(strict_types=1);

return [
    'name' => 'Barama Real Estate',
    'version' => APP_VERSION,
    'environment' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => (string) env('APP_URL', ''),
    'timezone' => (string) env('APP_TIMEZONE', 'Asia/Phnom_Penh'),
    'session_name' => (string) env('SESSION_NAME', 'barama_session'),
];
