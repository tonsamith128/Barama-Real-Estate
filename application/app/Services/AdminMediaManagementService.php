<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\MediaRepository;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class AdminMediaManagementService
{
    public function updateAltText(int $mediaId, ?string $altText): void
    {
        $auth = new AuthService();
        $actor = $auth->user();

        if (
            $actor === null
            || !$auth->hasRole('administrator')
            || !$auth->can('media.manage')
        ) {
            throw new DomainException('Administrator media management permission is required.');
        }

        $media = new MediaRepository();
        $asset = $media->asset($mediaId);
        if ($asset === null) {
            throw new InvalidArgumentException('Media asset was not found.');
        }
        if (!str_starts_with((string) $asset['mime_type'], 'image/')) {
            throw new DomainException('Alt text can only be managed for image media.');
        }

        $altText = $altText !== null ? trim($altText) : null;
        if ($altText === '') {
            $altText = null;
        }
        if ($altText !== null && mb_strlen($altText) > 255) {
            throw new InvalidArgumentException('Alt text cannot exceed 255 characters.');
        }

        $media->updateAltText($mediaId, $altText);
        (new SecurityAuditService())->record(
            'admin.media_alt_text_updated',
            (int) $actor['id'],
            null,
            ['media_id' => $mediaId, 'has_alt_text' => $altText !== null]
        );
    }

    public function deleteOrphanAsset(int $mediaId): void
    {
        $auth = new AuthService();
        if (!$auth->check() || !$auth->hasRole('administrator') || !$auth->can('media.manage')) {
            throw new DomainException('Administrator media management permission is required.');
        }

        $media = new MediaRepository();
        $asset = $media->asset($mediaId);
        if ($asset === null) {
            throw new InvalidArgumentException('Media asset was not found.');
        }

        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            if ($media->attachmentCountForMedia($mediaId) !== 0) {
                throw new DomainException('Attached real-estate media cannot be deleted from Platform Data. Remove it through the owning record workflow instead.');
            }
            $media->deleteAsset($mediaId);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        $disk = strtolower(trim((string) ($asset['disk'] ?? 'private')));
        $path = (string) $asset['path'];

        if (in_array($disk, ['s3', 'r2'], true)) {
            try {
                $cloud = new CloudObjectStorageService($disk);
                $cloud->delete($cloud->objectKey($path));
            } catch (Throwable $exception) {
                error_log('Barama orphan cloud media cleanup could not remove ' . $path . ': ' . $exception->getMessage());
            }
            return;
        }

        $storageRoot = realpath(APP_PATH . '/storage/uploads');
        $absolute = realpath(APP_PATH . '/storage/uploads/' . ltrim($path, '/'));
        if (
            $storageRoot !== false
            && $absolute !== false
            && str_starts_with($absolute, $storageRoot . DIRECTORY_SEPARATOR)
            && is_file($absolute)
            && !@unlink($absolute)
        ) {
            error_log('Barama orphan media cleanup could not remove: ' . $absolute);
        }
    }
}
