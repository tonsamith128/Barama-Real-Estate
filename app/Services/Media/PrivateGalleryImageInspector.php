<?php

declare(strict_types=1);

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Shared server-side validation for listing and project private galleries. */
final class PrivateGalleryImageInspector
{
    public const MAX_BYTES = 12 * 1024 * 1024;

    /**
     * @return array{sha:string, mime:string, name:string, info:array<int|string, mixed>}
     */
    public function inspect(UploadedFile $file): array
    {
        if (!$file->isValid() || $file->getSize() < 1 || $file->getSize() > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'image' => 'Please upload an image no larger than 12 MB.',
            ]);
        }

        $source = $file->getRealPath();
        $info = $source ? @getimagesize($source) : false;
        $formats = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!is_array($info) || !isset($formats[$info['mime'] ?? ''])
            || (int) $info[0] < 1 || (int) $info[1] < 1
            || (int) $info[0] > 8000 || (int) $info[1] > 8000
            || (int) $info[0] * (int) $info[1] > 24000000) {
            throw ValidationException::withMessages([
                'image' => 'Only valid JPEG, PNG or WebP images up to 24 megapixels are accepted.',
            ]);
        }

        $sha = hash_file('sha256', $source);
        if (!is_string($sha)) {
            throw new RuntimeException('Could not validate image integrity.');
        }

        return [
            'sha' => $sha,
            'mime' => $info['mime'],
            'name' => $sha.'.'.$formats[$info['mime']],
            'info' => $info,
        ];
    }
}
