<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\MediaRepository;
use Barama\Repositories\WorkspaceSettingsRepository;
use Barama\Support\IdGenerator;
use DomainException;
use finfo;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class SiteMediaStorageService
{
    /**
     * @param array<string,mixed> $file
     * @return array{id:int,uuid:string,mime_type:string}
     */
    public function store(array $file, string $collection): array
    {
        $auth = new AuthService();
        $user = $auth->user();

        if (
            $user === null
            || !$auth->hasRole('administrator')
            || !$auth->can('page.manage')
        ) {
            throw new DomainException('Administrator homepage media permission is required.');
        }

        $collection = strtolower(trim($collection));

        $isHomepageCollection = in_array($collection, [
            'homepage_hero_image',
            'homepage_hero_slider',
            'homepage_hero_video',
        ], true);
        $isMarketplaceCollection = preg_match(
            '/^marketplace_(properties|projects|companies)_hero_(image|slider|video)$/',
            $collection
        ) === 1;

        if (!$isHomepageCollection && !$isMarketplaceCollection) {
            throw new InvalidArgumentException('Unsupported site hero media collection.');
        }

        $isVideoCollection = str_ends_with($collection, '_hero_video');

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('The uploaded homepage media is incomplete or invalid.');
        }

        $temporaryPath = (string) ($file['tmp_name'] ?? '');

        if (
            $temporaryPath === ''
            || !is_file($temporaryPath)
            || (!defined('BARAMA_CLI') && !is_uploaded_file($temporaryPath))
        ) {
            throw new InvalidArgumentException('The uploaded homepage media could not be verified.');
        }

        $actualSize = filesize($temporaryPath);

        if ($actualSize === false) {
            throw new InvalidArgumentException('The uploaded homepage media size could not be verified.');
        }

        $size = (int) $actualSize;
        $maxBytes = max(
            1,
            (int) config('domain.site_media_max_upload_bytes', 50 * 1024 * 1024)
        );

        $adminStorageConfig = (new AdminSettingsService())->storageConfig();
        $adminLimitMb = (int) $adminStorageConfig['site_media_upload_limit_mb'];

        $maxBytes = min(
            $maxBytes,
            $adminLimitMb * 1024 * 1024
        );

        if ($size < 1 || $size > $maxBytes) {
            throw new InvalidArgumentException('The uploaded homepage media exceeds the allowed size.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($temporaryPath);
        $allowed = (array) config('domain.site_media_allowed_mime_types', []);

        if (!isset($allowed[$mime])) {
            throw new InvalidArgumentException('This homepage media type is not allowed.');
        }

        if (
            !$isVideoCollection
            && !str_starts_with($mime, 'image/')
        ) {
            throw new InvalidArgumentException('Hero image and slider uploads must be images.');
        }

        if (
            $isVideoCollection
            && !str_starts_with($mime, 'video/')
        ) {
            throw new InvalidArgumentException('Hero video upload must be a supported video file.');
        }

        $extension = (string) $allowed[$mime];
        $width = null;
        $height = null;

        if (str_starts_with($mime, 'image/')) {
            $dimensions = @getimagesize($temporaryPath);

            if (!is_array($dimensions) || !isset($dimensions[0], $dimensions[1])) {
                throw new InvalidArgumentException('The uploaded homepage image is invalid.');
            }

            $width = (int) $dimensions[0];
            $height = (int) $dimensions[1];
            $maxPixels = max(
                1,
                (int) config('domain.media_max_image_pixels', 50_000_000)
            );

            if ($width < 1 || $height < 1 || ($width * $height) > $maxPixels) {
                throw new InvalidArgumentException('The uploaded homepage image dimensions are not allowed.');
            }
        }

        $relativeDirectory = date('Y/m');
        $directory = APP_PATH . '/storage/uploads/private/' . $relativeDirectory;

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Homepage media storage directory could not be created.');
        }

        $filename = str_replace('-', '', IdGenerator::uuid()) . '.' . $extension;
        $destination = $directory . '/' . $filename;

        if (defined('BARAMA_CLI')) {
            $moved = copy($temporaryPath, $destination);

            if ($moved) {
                @unlink($temporaryPath);
            }
        } else {
            $moved = move_uploaded_file($temporaryPath, $destination);
        }

        if (!$moved) {
            throw new RuntimeException('The uploaded homepage media could not be stored.');
        }

        @chmod($destination, 0640);

        $relativePath = 'private/' . $relativeDirectory . '/' . $filename;
        $repository = new MediaRepository();
        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $mediaId = $repository->create([
                'created_by_user_id' => (int) $user['id'],
                'path' => $relativePath,
                'original_name' => basename((string) ($file['name'] ?? 'homepage-media.' . $extension)),
                'mime_type' => $mime,
                'extension' => $extension,
                'size_bytes' => $size,
                'width_px' => $width,
                'height_px' => $height,
                'alt_text' => null,
                'checksum_sha256' => hash_file('sha256', $destination),
            ]);

            $repository->attach(
                $mediaId,
                'site',
                1,
                $collection,
                0,
                false
            );

            $asset = $repository->asset($mediaId);

            if ($asset === null) {
                throw new RuntimeException('Stored homepage media could not be reloaded.');
            }

            $pdo->commit();

            (new SecurityAuditService())->record(
                'admin.homepage_media_uploaded',
                (int) $user['id'],
                null,
                [
                    'media_id' => $mediaId,
                    'collection' => $collection,
                    'mime_type' => $mime,
                    'size_bytes' => $size,
                ]
            );

            return [
                'id' => $mediaId,
                'uuid' => (string) $asset['uuid'],
                'mime_type' => $mime,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            @unlink($destination);
            throw $exception;
        }
    }

    /**
     * Normalize a standard PHP multiple-file input and store up to 12 files.
     *
     * @param array<string,mixed> $files
     * @return array<int,array{id:int,uuid:string,mime_type:string}>
     */
    public function storeMany(array $files, string $collection): array
    {
        $names = is_array($files['name'] ?? null) ? array_values($files['name']) : [];
        $types = is_array($files['type'] ?? null) ? array_values($files['type']) : [];
        $tmpNames = is_array($files['tmp_name'] ?? null) ? array_values($files['tmp_name']) : [];
        $errors = is_array($files['error'] ?? null) ? array_values($files['error']) : [];
        $sizes = is_array($files['size'] ?? null) ? array_values($files['size']) : [];
        $stored = [];

        foreach (array_slice($names, 0, 12) as $index => $name) {
            $error = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);

            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $stored[] = $this->store([
                'name' => $name,
                'type' => $types[$index] ?? '',
                'tmp_name' => $tmpNames[$index] ?? '',
                'error' => $error,
                'size' => $sizes[$index] ?? 0,
            ], $collection);
        }

        return $stored;
    }
}
