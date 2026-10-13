<?php

declare(strict_types=1);

namespace Barama\Services;

use InvalidArgumentException;
use RuntimeException;

final class PaymentMethodAssetService
{
    private const MAX_QR_BYTES = 5242880;

    /** @param array<string,mixed> $file */
    public function storeQr(array $file, ?string $existingPath = null): ?string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return $existingPath;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('QR code upload failed. Please choose the image again.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        if ($tmp === '' || !is_file($tmp)) {
            throw new InvalidArgumentException('The uploaded QR image is unavailable.');
        }
        if ($size < 1 || $size > self::MAX_QR_BYTES) {
            throw new InvalidArgumentException('QR code image must be 5 MB or smaller.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        $extensions = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        ];
        if (!isset($extensions[$mime])) {
            throw new InvalidArgumentException('QR code must be a PNG, JPG, or WebP image.');
        }

        $base = defined('BASE_PATH') ? rtrim((string) BASE_PATH, '/') : dirname(__DIR__, 3);
        $relativeDirectory = '/uploads/payment-methods/qr';
        $directory = $base . $relativeDirectory;
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create the payment QR storage directory.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $destination = $directory . '/' . $filename;
        if (!move_uploaded_file($tmp, $destination)) {
            throw new RuntimeException('Could not store the QR code image.');
        }
        @chmod($destination, 0644);

        $newPath = $relativeDirectory . '/' . $filename;
        if (
            is_string($existingPath)
            && str_starts_with($existingPath, $relativeDirectory . '/')
            && $existingPath !== $newPath
        ) {
            $old = $base . $existingPath;
            if (is_file($old)) {
                @unlink($old);
            }
        }

        return $newPath;
    }

    public function removeQr(?string $existingPath): void
    {
        if (!is_string($existingPath) || $existingPath === '') {
            return;
        }
        $prefix = '/uploads/payment-methods/qr/';
        if (!str_starts_with($existingPath, $prefix)) {
            return;
        }
        $base = defined('BASE_PATH') ? rtrim((string) BASE_PATH, '/') : dirname(__DIR__, 3);
        $path = $base . $existingPath;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
