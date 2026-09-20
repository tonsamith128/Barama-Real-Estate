<?php

declare(strict_types=1);

return [
    'from_address' => (string) env('MAIL_FROM_ADDRESS', ''),
    'from_name' => (string) env('MAIL_FROM_NAME', 'Barama Real Estate'),
];
