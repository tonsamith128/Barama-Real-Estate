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

        $storageRoot = realpath(APP_PATH . '/storage/uploads');
        $absolute = realpath(APP_PATH . '/storage/uploads/' . ltrim((string) $asset['path'], '/'));

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