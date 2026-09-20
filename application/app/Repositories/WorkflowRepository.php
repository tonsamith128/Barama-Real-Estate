<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use InvalidArgumentException;
use PDO;

final class WorkflowRepository
{
    /** @return array<string, mixed>|null */
    public function context(string $resource, int $id): ?array
    {
        [$sql, $params] = match ($resource) {
            'company' => [
                'SELECT id, status, id AS company_id
                 FROM companies
                 WHERE id = :id AND deleted_at IS NULL LIMIT 1',
                ['id' => $id],
            ],
            'project' => [
                'SELECT id, status, company_id
                 FROM projects
                 WHERE id = :id AND deleted_at IS NULL LIMIT 1',
                ['id' => $id],
            ],
            'property' => [
                'SELECT id, status, company_id
                 FROM properties
                 WHERE id = :id AND deleted_at IS NULL LIMIT 1',
                ['id' => $id],
            ],
            'listing' => [
                'SELECT listings.id, listings.status, properties.company_id
                 FROM listings
                 INNER JOIN properties ON properties.id = listings.property_id
                 WHERE listings.id = :id
                   AND listings.deleted_at IS NULL
                   AND properties.deleted_at IS NULL
                 LIMIT 1',
                ['id' => $id],
            ],
            'inquiry' => [
                'SELECT id, status, company_id
                 FROM inquiries
                 WHERE id = :id LIMIT 1',
                ['id' => $id],
            ],
            'lead' => [
                'SELECT id, stage AS status, company_id
                 FROM leads
                 WHERE id = :id AND deleted_at IS NULL LIMIT 1',
                ['id' => $id],
            ],
            default => throw new InvalidArgumentException("Unsupported workflow resource [{$resource}]."),
        };

        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function updateStatus(string $resource, int $id, string $status): void
    {
        [$table, $column] = match ($resource) {
            'company' => ['companies', 'status'],
            'project' => ['projects', 'status'],
            'property' => ['properties', 'status'],
            'listing' => ['listings', 'status'],
            'inquiry' => ['inquiries', 'status'],
            'lead' => ['leads', 'stage'],
            default => throw new InvalidArgumentException("Unsupported workflow resource [{$resource}]."),
        };

        $additional = '';

        if ($resource === 'company' && $status === 'approved') {
            $additional = ', verified_at = COALESCE(verified_at, CURRENT_TIMESTAMP), published_at = COALESCE(published_at, CURRENT_TIMESTAMP)';
        } elseif ($resource === 'project' && $status === 'published') {
            $additional = ', published_at = COALESCE(published_at, CURRENT_TIMESTAMP)';
        } elseif ($resource === 'listing' && $status === 'published') {
            $additional = ', published_at = COALESCE(published_at, CURRENT_TIMESTAMP), closed_at = NULL';
        } elseif ($resource === 'listing' && $status === 'closed') {
            $additional = ', closed_at = CURRENT_TIMESTAMP';
        } elseif ($resource === 'inquiry' && $status === 'replied') {
            $additional = ', responded_at = COALESCE(responded_at, CURRENT_TIMESTAMP)';
        } elseif ($resource === 'inquiry' && $status === 'closed') {
            $additional = ', closed_at = CURRENT_TIMESTAMP';
        } elseif ($resource === 'lead' && $status === 'won') {
            $additional = ', won_at = CURRENT_TIMESTAMP, lost_at = NULL';
        } elseif ($resource === 'lead' && $status === 'lost') {
            $additional = ', lost_at = CURRENT_TIMESTAMP, won_at = NULL';
        }

        $sql = "UPDATE {$table}
                SET {$column} = :status,
                    updated_at = CURRENT_TIMESTAMP
                    {$additional}
                WHERE id = :id";

        $statement = Database::connection()->prepare($sql);
        $statement->execute([
            'status' => $status,
            'id' => $id,
        ]);
    }

    public function record(
        string $resource,
        int $id,
        ?int $actorUserId,
        string $fromStatus,
        string $toStatus,
        ?string $note
    ): void {
        $statement = Database::connection()->prepare(
            'INSERT INTO entity_status_history
                (entity_type, entity_id, actor_user_id, from_status, to_status, note)
             VALUES
                (:entity_type, :entity_id, :actor_user_id, :from_status, :to_status, :note)'
        );
        $statement->execute([
            'entity_type' => $resource,
            'entity_id' => $id,
            'actor_user_id' => $actorUserId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
        ]);
    }
}
