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
    'documentation_url' => 'https://docs.tonsamith.com/docs?=228711-barama-real-estate-property-marketplace-developer-management-php-script',
    'support_url' => 'https://tonsamith.com/contact',
];
