<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use Barama\Support\IdGenerator;
use PDO;

final class PageRepository
{
    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO cms_pages
                (uuid, slug, title, excerpt, content_text, status,
                 seo_title, seo_description, robots_directive,
                 published_at, created_by_user_id, updated_by_user_id)
             VALUES
                (:uuid, :slug, :title, :excerpt, :content_text, :status,
                 :seo_title, :seo_description, :robots_directive,
                 :published_at, :created_by_user_id, :updated_by_user_id)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'slug' => $data['slug'],
            'title' => $data['title'],
            'excerpt' => $data['excerpt'],
            'content_text' => $data['content_text'],
            'status' => $data['status'],
            'seo_title' => $data['seo_title'],
            'seo_description' => $data['seo_description'],
            'robots_directive' => $data['robots_directive'],
            'published_at' => $data['status'] === 'published'
                ? date('Y-m-d H:i:s')
                : null,
            'created_by_user_id' => $data['user_id'],
            'updated_by_user_id' => $data['user_id'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE cms_pages
             SET slug = :slug,
                 title = :title,
                 excerpt = :excerpt,
                 content_text = :content_text,
                 seo_title = :seo_title,
                 seo_description = :seo_description,
                 robots_directive = :robots_directive,
                 updated_by_user_id = :updated_by_user_id
             WHERE id = :id'
        );
        $statement->execute([
            'slug' => $data['slug'],
            'title' => $data['title'],
            'excerpt' => $data['excerpt'],
            'content_text' => $data['content_text'],
            'seo_title' => $data['seo_title'],
            'seo_description' => $data['seo_description'],
            'robots_directive' => $data['robots_directive'],
            'updated_by_user_id' => $data['user_id'],
            'id' => $id,
        ]);
    }

    public function setStatus(int $id, string $status, int $userId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE cms_pages
             SET status = :status,
                 published_at = CASE
                    WHEN :status_publish = "published" AND published_at IS NULL
                        THEN CURRENT_TIMESTAMP
                    ELSE published_at
                 END,
                 updated_by_user_id = :updated_by_user_id
             WHERE id = :id'
        );
        $statement->execute([
            'status' => $status,
            'status_publish' => $status,
            'updated_by_user_id' => $userId,
            'id' => $id,
        ]);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT p.*, created_by.name AS created_by_name,
                    updated_by.name AS updated_by_name
             FROM cms_pages p
             LEFT JOIN users created_by ON created_by.id = p.created_by_user_id
             LEFT JOIN users updated_by ON updated_by.id = p.updated_by_user_id
             WHERE p.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function publishedBySlug(string $slug): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT p.*
             FROM cms_pages p
             WHERE p.slug = :slug
               AND p.status = "published"
               AND p.published_at IS NOT NULL
             LIMIT 1'
        );
        $statement->execute(['slug' => trim($slug)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM cms_pages WHERE slug = :slug';
        $params = ['slug' => $slug];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn() > 0;
    }
}
