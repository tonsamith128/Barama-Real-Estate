<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use PDO;

final class PropertyRepository
{
    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM properties
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $property = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($property) ? $property : null;
    }

    /** @return array<string, mixed>|null */
    public function findApprovedBySlug(string $slug): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT properties.*,
                    locations.name AS location_name,
                    locations.slug AS location_slug,
                    projects.name AS project_name,
                    projects.slug AS project_slug,
                    companies.name AS company_name,
                    companies.slug AS company_slug
             FROM properties
             LEFT JOIN locations ON locations.id = properties.location_id
             LEFT JOIN projects
                ON projects.id = properties.project_id
               AND projects.deleted_at IS NULL
             LEFT JOIN companies ON companies.id = properties.company_id
             WHERE properties.slug = :slug
               AND properties.status = :property_status
               AND properties.deleted_at IS NULL
               AND (
                    properties.company_id IS NULL
                    OR (
                        companies.status = :company_status
                        AND companies.published_at IS NOT NULL
                        AND companies.deleted_at IS NULL
                    )
               )
             LIMIT 1'
        );
        $statement->execute([
            'slug' => trim($slug),
            'property_status' => 'approved',
            'company_status' => 'approved',
        ]);

        $property = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($property) ? $property : null;
    }
}
