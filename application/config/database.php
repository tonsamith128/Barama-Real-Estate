<?php

declare(strict_types=1);

return [
    'driver' => 'mysql',
    'host' => (string) env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', 3306),
    'database' => (string) env('DB_DATABASE', ''),
    'username' => (string) env('DB_USERNAME', ''),
    'password' => (string) env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
];
