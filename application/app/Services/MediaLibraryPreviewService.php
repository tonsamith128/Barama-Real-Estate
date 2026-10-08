<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Core\Request;
use Barama\Core\Response;
use PDO;

final class MediaLibraryPreviewService
{
    /** @var array<int,string> */
    private const INLINE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'application/pdf',
        'video/mp4',
        'video/webm',
        'video/quicktime',
        'video/ogg',
        'audio/mpeg',
        'audio/wav',
        'audio/x-wav',
        'audio/mp4',
        'audio/x-m4a',
        'audio/ogg',
        'audio/flac',
        'audio/x-flac',
        'text/plain',
        'text/csv',
    ];

    public function response(Request $request, string $uuid): Response
    {
        $uuid = trim($uuid);

        if (
            preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
                $uuid
            ) !== 1
        ) {
            return Response::html('', 404);
        }

        $statement = Database::connection()->prepare(
            'SELECT id, uuid, disk, path, original_name, mime_type, size_bytes,
                    width_px, height_px, alt_text
             FROM media_assets
             WHERE uuid = :uuid
               AND disk <> "external"
             LIMIT 1'
        );
        $statement->execute(['uuid' => $uuid]);
        $media = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($media)) {
            return Response::html('', 404);
        }

        $storageRoot = realpath(APP_PATH . '/storage/uploads');
        $path = realpath(
            APP_PATH . '/storage/uploads/' . ltrim((string) $media['path'], '/')
        );

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

        $mime = strtolower(trim((string) $media['mime_type']));
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }

        $inline = in_array($mime, self::INLINE_MIME_TYPES, true);
        $name = trim((string) $media['original_name']);
        $name = $name !== '' ? $name : 'media';
        $name = str_replace(["\r", "\n"], '', $name);

        $headers = [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "frame-ancestors 'self'",
            'Content-Disposition' => ($inline ? 'inline' : 'attachment')
                . "; filename*=UTF-8''" . rawurlencode($name),
        ];

        $rangeCapable = str_starts_with($mime, 'video/')
            || str_starts_with($mime, 'audio/');

        if ($rangeCapable) {
            $headers['Accept-Ranges'] = 'bytes';
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
                    $content = file_get_contents(
                        $path,
                        false,
                        null,
                        $start,
                        $length
                    );

                    if (is_string($content)) {
                        return Response::binary(
                            $content,
                            $mime,
                            array_merge($headers, [
                                'Content-Length' => (string) $length,
                                'Content-Range' => 'bytes '
                                    . $start
                                    . '-'
                                    . $end
                                    . '/'
                                    . $size,
                            ]),
                            206
                        );
                    }
                }
            }
        }

        $content = file_get_contents($path);
        if (!is_string($content)) {
            return Response::html('', 404);
        }

        return Response::binary(
            $content,
            $mime,
            array_merge($headers, [
                'Content-Length' => (string) strlen($content),
            ])
        );
    }
}
