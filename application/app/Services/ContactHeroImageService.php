<?php

declare(strict_types=1);

namespace Barama\Services;

use DomainException;
use InvalidArgumentException;
use RuntimeException;

/** A purpose-limited, administrator-only upload to a public raster-image folder. */
final class ContactHeroImageService
{
    private const DIRECTORY = '/assets/images/contact/custom';

    /** @param array<string,mixed> $file */
    public function store(array $file): string
    {
        $auth = new AuthService();
        if ($auth->user() === null || !$auth->hasRole('administrator') || !$auth->can('page.manage')) {
            throw new DomainException('Administrator Contact page management permission is required.');
        }
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Select a valid Contact Hero image.');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp) || !is_uploaded_file($tmp)) {
            throw new InvalidArgumentException('The uploaded Contact Hero image could not be verified.');
        }
        $size = filesize($tmp);
        if ($size === false || $size < 1 || $size > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('Contact Hero images must be 5 MB or smaller.');
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $allowed = ['image/jpeg' => ['jpg', IMAGETYPE_JPEG], 'image/png' => ['png', IMAGETYPE_PNG], 'image/webp' => ['webp', IMAGETYPE_WEBP]];
        if (!isset($allowed[$mime])) {
            throw new InvalidArgumentException('Only genuine JPG, PNG, and WebP photos are allowed.');
        }
        $dimensions = @getimagesize($tmp);
        if (!is_array($dimensions) || (int) ($dimensions[2] ?? 0) !== $allowed[$mime][1]) {
            throw new InvalidArgumentException('Image contents do not match the recognized image type.');
        }
        $width = (int) ($dimensions[0] ?? 0);
        $height = (int) ($dimensions[1] ?? 0);
        if ($width < 600 || $height < 400 || $width > 8000 || $height > 8000 || $width * $height > 28_000_000) {
            throw new InvalidArgumentException('Use an image at least 600×400 and no larger than 28 megapixels.');
        }
        $dir = BASE_PATH . self::DIRECTORY;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Contact Hero image directory is unavailable.');
        }
        if (is_link($dir) || !is_writable($dir)) {
            throw new RuntimeException('Contact Hero image directory is unsafe or not writable.');
        }
        $path = self::DIRECTORY . '/' . bin2hex(random_bytes(16)) . '.' . $allowed[$mime][0];
        $absolutePath = BASE_PATH . $path;
        if (!move_uploaded_file($tmp, $absolutePath)) {
            throw new RuntimeException('Could not store the uploaded Contact Hero image.');
        }
        chmod($absolutePath, 0644);
        return $path;
    }

    public function removeNewlyUploaded(string $path): void
    {
        // Never delete unrelated files or a bundled default.
        if (preg_match('~^/assets/images/contact/custom/[a-f0-9]{32}\.(?:jpg|png|webp)$~D', $path) === 1) {
            $dest = BASE_PATH . $path;
            if (is_file($dest) && !is_link($dest)) {
                @unlink($dest);
            }
        }
    }
}
