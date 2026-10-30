<?php

declare(strict_types=1);

return [
    // Independently pinned 2026-10-24 publisher key fingerprint.
    'publisher_fingerprint' => 'be53fa7e3e531da5a7a21e9f12eaaf15dfcf8633af4fd65640d27bb21bb03287',
    // The publisher's public key is provisioned by the server operator, never by the upload form.
    'publisher_public_key' => env('BARAMA_PUBLISHER_PUBLIC_KEY_B64', ''),
    // Commercial publisher entitlement is not yet migrated; no production import.
    'enable_staging_import' => env('BARAMA_ENABLE_STAGING_DEMO_IMPORT', false),
];
