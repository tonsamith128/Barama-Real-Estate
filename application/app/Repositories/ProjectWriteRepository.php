<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use Barama\Support\IdGenerator;
use PDO;

final class ProjectWriteRepository
{
    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO projects
                (uuid, company_id, location_id, name, slug, project_type, status,
                 completion_status, address_line, description, launch_date,
                 expected_completion_date)
             VALUES
                (:uuid, :company_id, :location_id, :name, :slug, :project_type, :status,
                 :completion_status, :address_line, :description, :launch_date,
                 :expected_completion_date)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'company_id' => $data['company_id'],
            'location_id' => $data['location_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'project_type' => $data['project_type'],
            'status' => 'draft',
            'completion_status' => $data['completion_status'],
            'address_line' => $data['address_line'],
            'description' => $data['description'],
            'launch_date' => $data['launch_date'],
            'expected_completion_date' => $data['expected_completion_date'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE projects
             SET location_id = :location_id,
                 name = :name,
                 project_type = :project_type,
                 completion_status = :completion_status,
                 address_line = :address_line,
                 description = :description,
                 launch_date = :launch_date,
                 expected_completion_date = :expected_completion_date,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $statement->execute(array_merge($data, ['id' => $id]));
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM projects WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $project = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($project) ? $project : null;
    }

    public function slugExists(string $slug): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM projects WHERE slug = :slug LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);

        return $statement->fetchColumn() !== false;
    }
}
