<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use DomainException;
use PDO;

final class EmailTemplateMediaLibraryService
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function items(string $query = '', string $type = 'all'): array
    {
        $this->administrator();

        $query = mb_substr(trim($query), 0, 100);
        $type = strtolower(trim($type));
        if (!in_array($type, ['all', 'image', 'video'], true)) {
            $type = 'all';
        }

        $where = ['(m.mime_type LIKE "image/%" OR m.mime_type LIKE "video/%")'];
        $params = [];

        if ($type === 'image') {
            $where[] = 'm.mime_type LIKE "image/%"';
        } elseif ($type === 'video') {
            $where[] = 'm.mime_type LIKE "video/%"';
        }

        if ($query !== '') {
            $where[] = '(m.original_name LIKE :query OR COALESCE(m.alt_text, "") LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        $statement = Database::connection()->prepare(
            'SELECT m.id, m.uuid, m.original_name, m.mime_type,
                    m.size_bytes, m.width_px, m.height_px, m.alt_text,
                    m.created_at
             FROM media_assets m
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT 60'
        );
        $statement->execute($params);

        $items = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $uuid = trim((string) ($row['uuid'] ?? ''));
            if ($uuid === '') {
                continue;
            }

            $mimeType = strtolower((string) ($row['mime_type'] ?? ''));
            $mediaType = str_starts_with($mimeType, 'video/') ? 'video' : 'image';
            $publicUrl = url('/media/' . rawurlencode($uuid));

            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'uuid' => $uuid,
                'name' => (string) ($row['original_name'] ?? 'Media asset'),
                'mime_type' => $mimeType,
                'media_type' => $mediaType,
                'url' => $publicUrl,
                'alt_text' => trim((string) ($row['alt_text'] ?? '')),
                'width' => isset($row['width_px']) ? (int) $row['width_px'] : null,
                'height' => isset($row['height_px']) ? (int) $row['height_px'] : null,
                'size_bytes' => isset($row['size_bytes']) ? (int) $row['size_bytes'] : null,
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $items;
    }

    /** @return array<string,mixed> */
    private function administrator(): array
    {
        $auth = new AuthService();
        $user = $auth->user();

        if (
            $user === null
            || !$auth->hasRole('administrator')
            || !$auth->can('communication.manage')
            || !$auth->can('media.manage')
        ) {
            throw new DomainException(
                'Administrator communications and media permissions are required.'
            );
        }

        return $user;
    }
}
