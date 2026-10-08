<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use Barama\Support\IdGenerator;
use PDO;

final class EmailTemplateRepository
{
    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return Database::connection()
            ->query(
                'SELECT t.*, cu.name AS created_by_name, uu.name AS updated_by_name
                 FROM communication_email_templates t
                 LEFT JOIN users cu ON cu.id = t.created_by_user_id
                 LEFT JOIN users uu ON uu.id = t.updated_by_user_id
                 ORDER BY t.updated_at DESC, t.id DESC'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM communication_email_templates
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function findByKey(string $key): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT t.*, uu.name AS updated_by_name
             FROM communication_email_templates t
             LEFT JOIN users uu ON uu.id = t.updated_by_user_id
             WHERE t.template_key = :template_key
             LIMIT 1'
        );
        $statement->execute(['template_key' => strtolower(trim($key))]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function activeByKey(string $key): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM communication_email_templates
             WHERE template_key = :template_key
               AND status = "active"
             LIMIT 1'
        );
        $statement->execute(['template_key' => $key]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function keyExists(string $key, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*)
                FROM communication_email_templates
                WHERE template_key = :template_key';
        $params = ['template_key' => $key];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO communication_email_templates
                (uuid, template_key, name, description, subject_template,
                 html_template, text_template, status,
                 created_by_user_id, updated_by_user_id)
             VALUES
                (:uuid, :template_key, :name, :description, :subject_template,
                 :html_template, :text_template, "inactive",
                 :created_by_user_id, :updated_by_user_id)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'template_key' => $data['template_key'],
            'name' => $data['name'],
            'description' => $data['description'],
            'subject_template' => $data['subject_template'],
            'html_template' => $data['html_template'],
            'text_template' => $data['text_template'],
            'created_by_user_id' => $data['actor_user_id'],
            'updated_by_user_id' => $data['actor_user_id'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE communication_email_templates
             SET template_key = :template_key,
                 name = :name,
                 description = :description,
                 subject_template = :subject_template,
                 html_template = :html_template,
                 text_template = :text_template,
                 updated_by_user_id = :updated_by_user_id,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'template_key' => $data['template_key'],
            'name' => $data['name'],
            'description' => $data['description'],
            'subject_template' => $data['subject_template'],
            'html_template' => $data['html_template'],
            'text_template' => $data['text_template'],
            'updated_by_user_id' => $data['actor_user_id'],
        ]);
    }

    public function setStatus(int $id, string $status, int $actorUserId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE communication_email_templates
             SET status = :status,
                 updated_by_user_id = :updated_by_user_id,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'status' => $status,
            'updated_by_user_id' => $actorUserId,
        ]);
    }

    /** @return array<string,int> */
    public function summary(): array
    {
        $row = Database::connection()->query(
            'SELECT
                COUNT(*) AS total,
                SUM(status = "inactive") AS inactive,
                SUM(status = "active") AS active,
                SUM(status = "archived") AS archived
             FROM communication_email_templates'
        )->fetch(PDO::FETCH_ASSOC);

        return is_array($row)
            ? array_map(
                static fn (mixed $value): int => (int) ($value ?? 0),
                $row
            )
            : [
                'total' => 0,
                'inactive' => 0,
                'active' => 0,
                'archived' => 0,
            ];
    }
}
