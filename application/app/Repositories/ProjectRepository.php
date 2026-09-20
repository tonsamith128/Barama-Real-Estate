<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use PDO;

final class ProjectRepository
{
    /** @return array<string, mixed>|null */
    public function findPublicBySlug(string $slug): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT projects.*,
                    companies.name AS company_name,
                    companies.slug AS company_slug,
                    locations.name AS location_name,
                    locations.slug AS location_slug
             FROM projects
             INNER JOIN companies ON companies.id = projects.company_id
             LEFT JOIN locations ON locations.id = projects.location_id
             WHERE projects.slug = :slug
               AND projects.status = :project_status
               AND projects.published_at IS NOT NULL
               AND projects.deleted_at IS NULL
               AND companies.status = :company_status
               AND companies.published_at IS NOT NULL
               AND companies.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'slug' => trim($slug),
            'project_status' => 'published',
            'company_status' => 'approved',
        ]);

        $project = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($project) ? $project : null;
    }
}
