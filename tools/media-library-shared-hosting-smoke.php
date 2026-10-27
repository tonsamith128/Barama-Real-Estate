<?php

declare(strict_types=1);

// Contract regression for the validated v0.9.25 aaPanel media hotfix series.
// This is intentionally source-only: no production database, session, or file upload.
$root = dirname(__DIR__);
$routes = file_get_contents($root . '/application/routes/web.php');
$index = file_get_contents($root . '/application/resources/views/workspace/index.php');
$upload = file_get_contents($root . '/application/resources/views/workspace/media-upload.php');
$csrf = file_get_contents($root . '/application/app/Http/Middleware/CsrfMiddleware.php');
$picker = file_get_contents($root . '/assets/js/media-library-upload-v134.js');
$preview = file_get_contents($root . '/assets/js/media-library-index-v137.js');
$limits = file_get_contents($root . '/assets/js/media-library-upload-limits-v102.js');
foreach (compact('routes', 'index', 'upload', 'csrf', 'picker', 'preview', 'limits') as $name => $value) {
    if (!is_string($value)) {
        throw new RuntimeException('MEDIA_FIX_SOURCE_MISSING_' . $name);
    }
}
$check = static function (bool $ok, string $name): void {
    if (!$ok) {
        throw new RuntimeException('MEDIA_FIX_FAILED_' . $name);
    }
};
$check(substr_count($routes, "'/dashboard/media/upload'") === 2, 'UPLOAD_GET_POST');
$check(substr_count($routes, "'/dashboard/media/preview/{uuid}'") === 1, 'PRIVATE_PREVIEW');
$check(strpos($routes, "'/dashboard/media/upload'") < strpos($routes, "'/dashboard/{section}'"), 'ROUTE_ORDER');
$check(str_contains($routes, "PermissionMiddleware('media.manage')"), 'PERMISSION_GUARD');
$check(str_contains($routes, "new CsrfMiddleware()"), 'CSRF_GUARD');
$check(str_contains($index, "url('/dashboard/media/preview/' . rawurlencode($uuid))"), 'THUMBNAIL_URL');
$check(str_contains($index, 'data-media-preview'), 'THUMBNAIL_PREVIEW');
$check(str_contains($index, 'media-library-preview-repair-v101.css'), 'PREVIEW_STYLE');
$check(str_contains($picker, 'event.target !== input'), 'FILE_PICKER_RECURSION');
$check(str_contains($upload, 'csrf_field()') && str_contains($upload, 'multipart/form-data'), 'SECURE_UPLOAD_FORM');
$check(str_contains($upload, 'media-library-upload-contrast-v101.css'), 'LIGHT_DARK_CONTRAST');
$check(str_contains($upload, 'data-size-file-limit') && str_contains($upload, 'media-library-upload-limits-v102.js'), 'HOST_LIMIT');
$check(str_contains($limits, 'stopImmediatePropagation()'), 'OVERSIZE_CLIENT_REJECTION');
$check(str_contains($csrf, 'Csrf::validate($token)') && str_contains($csrf, 'BARAMA_MEDIA_UPLOAD_SIZE_GUARD_V102'), 'OVERSIZE_CSRF_REJECTION');
$check(str_contains($preview, 'BARAMA_MEDIA_THUMBNAIL_FALLBACK_V101'), 'THUMBNAIL_FALLBACK');
foreach ([
    'assets/css/media-library-preview-repair-v101.css',
    'assets/css/media-library-upload-contrast-v101.css',
    'assets/css/media-library-upload-limits-v102.css',
] as $file) {
    $check(is_file($root . '/' . $file), 'MISSING_ASSET_' . basename($file));
}
echo "BARAMA_MEDIA_SHARED_HOSTING_SMOKE=PASS\n";
