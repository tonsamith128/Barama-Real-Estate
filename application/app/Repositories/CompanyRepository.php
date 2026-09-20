<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use PDO;

final class CompanyRepository
{
    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM companies
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $company = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($company) ? $company : null;
    }

    /** @return array<string, mixed>|null */
    public function findPublicBySlug(string $slug): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT companies.*,
                    locations.name AS location_name,
                    locations.slug AS location_slug
             FROM companies
             LEFT JOIN locations ON locations.id = companies.location_id
             WHERE companies.slug = :slug
               AND companies.status = :status
               AND companies.published_at IS NOT NULL
               AND companies.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute([
            'slug' => trim($slug),
            'status' => 'approved',
        ]);

        $company = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($company) ? $company : null;
    }
}
