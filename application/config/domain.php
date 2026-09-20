<?php

declare(strict_types=1);

return [
    'inquiry_max_attempts' => 5,
    'inquiry_window_minutes' => 15,
    'media_max_upload_bytes' => 10 * 1024 * 1024,
    'media_allowed_mime_types' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ],
];
