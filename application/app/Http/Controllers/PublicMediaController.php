<?php

declare(strict_types=1);

namespace Barama\Http\Controllers;

use Barama\Core\Request;
use Barama\Core\Response;
use Barama\Repositories\MarketplaceRepository;
use Barama\Services\CloudObjectStorageService;
use RuntimeException;

final class PublicMediaController
{
    public function show(Request $request, string $uuid): Response
    {
        if (
            preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
                $uuid
            ) !== 1
        ) {
            return Response::html('', 404);
        }

        $media = (new MarketplaceRepository())->publicMedia($uuid);
        if ($media === null) {
            return Response::html('', 404);
        }

        $mimeType = (string) $media['mime_type'];
        $disk = strtolower(trim((string) ($media['disk'] ?? 'private')));
        $baseHeaders = [
            'Cache-Control' => 'public, max-age=86400, immutable',
            'Content-Disposition' => 'inline; filename="' . rawurlencode((string) $media['original_name']) . '"',
        ];

        if (in_array($disk, ['s3', 'r2'], true)) {
            try {
                $cloud = new CloudObjectStorageService($disk);
                $range = str_starts_with($mimeType, 'video/') ? $request->header('Range') : null;
                $result = $cloud->get($cloud->objectKey((string) $media['path']), is_string($range) ? $range : null);
            } catch (RuntimeException $exception) {
                error_log('Barama cloud media read failed: ' . $exception->getMessage());
                return Response::html('', 404);
            }

            if (!in_array($result['status'], [200, 206], true)) {
                return Response::html('', 404);
            }

            if (str_starts_with($mimeType, 'video/')) {
                $baseHeaders['Accept-Ranges'] = 'bytes';
            }
            if (isset($result['headers']['content-range'])) {
                $baseHeaders['Content-Range'] = $result['headers']['content-range'];
            }
            $baseHeaders['Content-Length'] = (string) strlen($result['body']);

            return Response::binary(
                $result['body'],
                $mimeType,
                $baseHeaders,
                $result['status']
            );
        }

        $storageRoot = realpath(APP_PATH . '/storage/uploads');
        $path = realpath(APP_PATH . '/storage/uploads/' . ltrim((string) $media['path'], '/'));
        if (
            $storageRoot === false
            || $path === false
            || !str_starts_with($path, $storageRoot . DIRECTORY_SEPARATOR)
            || !is_file($path)
        ) {
            return Response::html('', 404);
        }

        $size = filesize($path);
        if ($size === false || $size < 1) {
            return Response::html('', 404);
        }

        if (str_starts_with($mimeType, 'video/')) {
            $baseHeaders['Accept-Ranges'] = 'bytes';
            $range = $request->header('Range');
            if (
                is_string($range)
                && preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $matches) === 1
            ) {
                $startText = $matches[1];
                $endText = $matches[2];
                if ($startText === '' && $endText !== '') {
                    $suffixLength = min((int) $endText, $size);
                    $start = max(0, $size - $suffixLength);
                    $end = $size - 1;
                } else {
                    $start = $startText !== '' ? (int) $startText : 0;
                    $end = $endText !== '' ? (int) $endText : $size - 1;
                    $end = min($end, $size - 1);
                }
                if ($start >= 0 && $start <= $end && $start < $size) {
                    $length = $end - $start + 1;
                    $content = file_get_contents($path, false, null, $start, $length);
                    if (is_string($content)) {
                        return Response::binary($content, $mimeType, array_merge($baseHeaders, [
                            'Content-Length' => (string) $length,
                            'Content-Range' => 'bytes ' . $start . '-' . $end . '/' . $size,
                        ]), 206);
                    }
                }
            }
        }

        $content = file_get_contents($path);
        if (!is_string($content)) {
            return Response::html('', 404);
        }

        return Response::binary($content, $mimeType, array_merge($baseHeaders, [
            'Content-Length' => (string) strlen($content),
        ]));
    }
}
