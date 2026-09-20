<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use Barama\Support\IdGenerator;

final class MediaRepository
{
    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO media_assets
                (uuid, created_by_user_id, disk, path, original_name, mime_type,
                 extension, size_bytes, width_px, height_px, alt_text, checksum_sha256)
             VALUES
                (:uuid, :created_by_user_id, :disk, :path, :original_name, :mime_type,
                 :extension, :size_bytes, :width_px, :height_px, :alt_text, :checksum_sha256)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'created_by_user_id' => $data['created_by_user_id'],
            'disk' => 'private',
            'path' => $data['path'],
            'original_name' => $data['original_name'],
            'mime_type' => $data['mime_type'],
            'extension' => $data['extension'],
            'size_bytes' => $data['size_bytes'],
            'width_px' => $data['width_px'],
            'height_px' => $data['height_px'],
            'alt_text' => $data['alt_text'],
            'checksum_sha256' => $data['checksum_sha256'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function attach(
        int $mediaId,
        string $entityType,
        int $entityId,
        string $collection,
        int $position,
        bool $primary
    ): void {
        $statement = Database::connection()->prepare(
            'INSERT INTO media_attachments
                (media_asset_id, entity_type, entity_id, collection, position, is_primary)
             VALUES
                (:media_asset_id, :entity_type, :entity_id, :collection, :position, :is_primary)'
        );
        $statement->execute([
            'media_asset_id' => $mediaId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'collection' => $collection,
            'position' => $position,
            'is_primary' => $primary ? 1 : 0,
        ]);
    }
}
