<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use PDO;

final class LocationRepository
{
    /** @return array<string, mixed>|null */
    public function findActiveBySlug(string $slug): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, uuid, parent_id, type, country_code, name, slug,
                    latitude, longitude, status, sort_order
             FROM locations
             WHERE slug = :slug
               AND status = :status
             LIMIT 1'
        );
        $statement->execute([
            'slug' => trim($slug),
            'status' => 'active',
        ]);

        $location = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($location) ? $location : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function childrenOf(int $parentId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, uuid, parent_id, type, country_code, name, slug,
                    latitude, longitude, status, sort_order
             FROM locations
             WHERE parent_id = :parent_id
               AND status = :status
             ORDER BY sort_order ASC, name ASC'
        );
        $statement->execute([
            'parent_id' => $parentId,
            'status' => 'active',
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
