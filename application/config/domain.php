<?php

declare(strict_types=1);

return [
    'inquiry_max_attempts' => 5,
    'inquiry_window_minutes' => 15,
    'media_max_upload_bytes' => 10 * 1024 * 1024,
    'media_max_image_pixels' => 50_000_000,
    'site_media_max_upload_bytes' => 50 * 1024 * 1024,
    'site_media_allowed_mime_types' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/ogg' => 'ogv',
        'video/quicktime' => 'mov',
    ],
    'media_allowed_mime_types' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ],
];
