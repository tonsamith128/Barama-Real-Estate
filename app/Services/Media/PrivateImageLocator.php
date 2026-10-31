<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\MediaAsset;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class PrivateImageLocator
{
    public function response(MediaAsset $asset, bool $public = false): BinaryFileResponse
    {
        // The DB contains a relative PRIVATE path only, never a web URL.
        $path = (string) $asset->path;
        abort_unless(preg_match(
            '~^private/(demo|media)/([a-f0-9]{64})\.(jpg|jpeg|png|webp)$~D',
            $path, $match,
        ) === 1 && hash_equals($match[2], $asset->checksum_sha256), 404);

        $mimes = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png', 'webp' => 'image/webp',
        ];
        abort_unless(($mimes[$match[3]] ?? null) === $asset->mime_type, 404);

        $relative = substr($path, strlen('private/'));
        $disk = Storage::disk('local');
        $file = $disk->path($relative);
        abort_unless(
            $disk->exists($relative) && is_file($file) && !is_link($file)
            && !is_link(dirname($file)),
            404
        );

        return response()->file($file, [
            'Content-Type' => $asset->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => $public ? 'public, max-age=600' : 'private, no-store',
        ]);
    }
}
