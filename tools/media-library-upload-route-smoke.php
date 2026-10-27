<?php

declare(strict_types=1);

// Prevent regression of the missing routes in the 0.9.25 customer ZIP.
// The existing Media Library Upload button requires these protected endpoints.
$base = dirname(__DIR__);
$files = [
    'routes' => $base . '/application/routes/web.php',
    'controller' => $base . '/application/app/Http/Controllers/DashboardController.php',
    'view' => $base . '/application/resources/views/workspace/media-upload.php',
    'service' => $base . '/application/app/Services/MediaLibraryUploadService.php',
    'preview' => $base . '/application/app/Services/MediaLibraryPreviewService.php',
];
$sources = [];
foreach ($files as $label => $path) {
    $source = file_get_contents($path);
    if (!is_string($source)) {
        fwrite(STDERR, "FAIL missing source: {$label}\n");
        exit(1);
    }
    $sources[$label] = $source;
}

$checks = [
    'GET upload route' => ['routes', <<<'ROUTE'
$router->get(
    '/dashboard/media/upload',
    [DashboardController::class, 'mediaUpload'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new PermissionMiddleware('media.manage')]
);
ROUTE],
    'POST upload route and CSRF' => ['routes', <<<'ROUTE'
$router->post(
    '/dashboard/media/upload',
    [DashboardController::class, 'storeMediaUpload'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new PermissionMiddleware('media.manage'), new CsrfMiddleware()]
);
ROUTE],
    'protected preview' => ['routes', <<<'ROUTE'
$router->get(
    '/dashboard/media/preview/{uuid}',
    [DashboardController::class, 'mediaPreview'],
    [new AuthMiddleware(), new PermissionMiddleware('dashboard.access'), new PermissionMiddleware('media.manage')]
);
ROUTE],
    'GET handler' => ['controller', 'public function mediaUpload(Request $request): Response'],
    'POST handler' => ['controller', 'public function storeMediaUpload(Request $request): Response'],
    'preview handler' => ['controller', 'public function mediaPreview(Request $request, string $uuid): Response'],
    'form CSRF' => ['view', 'csrf_field()'],
    'multipart' => ['view', 'enctype="multipart/form-data" data-media-upload-form'],
    'dropzone' => ['view', 'data-media-dropzone'],
    'file input' => ['view', 'name="media_files[]"'],
    'MIME inspection' => ['service', 'new finfo(FILEINFO_MIME_TYPE)'],
    'permission guard' => ['service', "hasRole('administrator')"],
    'default private storage' => ['service', 'if ($disk === \'private\')'],
    'active cloud setting' => ['service', '(new CloudStorageService())->activeDisk()'],
    'optional cloud upload' => ['service', '$cloud->putFile('],
    'rollback cleanup' => ['service', 'array_reverse($writtenPaths)'],
    'cloud preview' => ['preview', 'new CloudObjectStorageService($disk)'],
];
foreach ($checks as $name => [$label, $needle]) {
    if (!str_contains($sources[$label], $needle)) {
        fwrite(STDERR, "FAIL: {$name}\n");
        exit(1);
    }
}
foreach (['get', 'post'] as $method) {
    $pattern = '/\\$router->' . $method . '\\(\\s*\'\\/dashboard\\/media\\/upload\'/m';
    if (preg_match_all($pattern, $sources['routes']) !== 1) {
        fwrite(STDERR, "FAIL: duplicate or missing {$method} media-upload route\n");
        exit(1);
    }
}
echo "MEDIA_LIBRARY_UPLOAD_ROUTE_SMOKE=PASS\n";
