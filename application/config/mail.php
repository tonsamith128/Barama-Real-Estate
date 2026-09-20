<?php

declare(strict_types=1);

return [
    'driver' => strtolower((string) env('MAIL_DRIVER', 'mail')),
    'from_address' => (string) env('MAIL_FROM_ADDRESS', ''),
    'from_name' => (string) env('MAIL_FROM_NAME', 'Barama Real Estate'),
    'host' => (string) env('MAIL_HOST', ''),
    'port' => (int) env('MAIL_PORT', 587),
    'encryption' => strtolower((string) env('MAIL_ENCRYPTION', 'tls')),
    'username' => (string) env('MAIL_USERNAME', ''),
    'password' => (string) env('MAIL_PASSWORD', ''),
    'timeout' => max(3, (int) env('MAIL_TIMEOUT', 15)),
    'max_attempts' => max(1, (int) env('MAIL_MAX_ATTEMPTS', 5)),
    'retry_minutes' => max(1, (int) env('MAIL_RETRY_MINUTES', 10)),
    'processing_stale_minutes' => max(5, (int) env('MAIL_PROCESSING_STALE_MINUTES', 15)),
];
