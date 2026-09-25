<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use Barama\Support\IdGenerator;
use PDO;

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
        if ($primary) {
            $this->clearPrimary($entityType, $entityId, $collection);
        }

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

    /** @return array<string,mixed>|null */
    public function attachment(int $attachmentId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT ma.*, m.uuid, m.path, m.original_name, m.mime_type, m.disk,
                    m.alt_text
             FROM media_attachments ma
             INNER JOIN media_assets m ON m.id = ma.media_asset_id
             WHERE ma.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $attachmentId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function updateAltText(int $mediaId, ?string $altText): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE media_assets
             SET alt_text = :alt_text
             WHERE id = :id'
        );
        $statement->execute([
            'alt_text' => $altText,
            'id' => $mediaId,
        ]);
    }

    public function setPrimary(int $attachmentId): void
    {
        $attachment = $this->attachment($attachmentId);

        if ($attachment === null) {
            return;
        }

        $this->clearPrimary(
            (string) $attachment['entity_type'],
            (int) $attachment['entity_id'],
            (string) $attachment['collection']
        );

        $statement = Database::connection()->prepare(
            'UPDATE media_attachments SET is_primary = 1 WHERE id = :id'
        );
        $statement->execute(['id' => $attachmentId]);
    }

    public function deleteAttachment(int $attachmentId): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM media_attachments WHERE id = :id'
        );
        $statement->execute(['id' => $attachmentId]);
    }

    public function attachmentCountForMedia(int $mediaId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM media_attachments WHERE media_asset_id = :media_id'
        );
        $statement->execute(['media_id' => $mediaId]);

        return (int) $statement->fetchColumn();
    }

    public function deleteAsset(int $mediaId): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM media_assets WHERE id = :id'
        );
        $statement->execute(['id' => $mediaId]);
    }

    private function clearPrimary(string $entityType, int $entityId, string $collection): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE media_attachments
             SET is_primary = 0
             WHERE entity_type = :entity_type
               AND entity_id = :entity_id
               AND collection = :collection'
        );
        $statement->execute([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'collection' => $collection,
        ]);
    }
}
