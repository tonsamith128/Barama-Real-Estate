<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use Barama\Support\IdGenerator;
use PDO;

final class EmailTemplateLocalizationRepository
{
    /** @return array<string,mixed>|null */
    public function variantForKey(string $key, string $locale): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT v.*, t.template_key, t.name AS base_name, t.description AS base_description, t.status AS base_status
             FROM communication_email_template_variants v
             INNER JOIN communication_email_templates t ON t.id = v.template_id
             WHERE t.template_key = :template_key AND v.locale = :locale
             LIMIT 1'
        );
        $statement->execute([
            'template_key' => strtolower(trim($key)),
            'locale' => strtolower(trim($locale)),
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function activeVariantByKey(string $key, string $locale): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT v.*, t.template_key
             FROM communication_email_template_variants v
             INNER JOIN communication_email_templates t ON t.id = v.template_id
             WHERE t.template_key = :template_key
               AND t.status = "active"
               AND v.locale = :locale
               AND v.status = "active"
             LIMIT 1'
        );
        $statement->execute([
            'template_key' => strtolower(trim($key)),
            'locale' => strtolower(trim($locale)),
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $data */
    public function createVariant(int $templateId, string $locale, array $data, int $actorUserId): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO communication_email_template_variants
                (uuid, template_id, locale, subject_template, html_template, text_template,
                 status, created_by_user_id, updated_by_user_id)
             VALUES
                (:uuid, :template_id, :locale, :subject_template, :html_template, :text_template,
                 "inactive", :created_by_user_id, :updated_by_user_id)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'template_id' => $templateId,
            'locale' => $locale,
            'subject_template' => $data['subject_template'],
            'html_template' => $data['html_template'],
            'text_template' => $data['text_template'],
            'created_by_user_id' => $actorUserId,
            'updated_by_user_id' => $actorUserId,
        ]);
        return (int) Database::connection()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function updateVariant(int $id, array $data, int $actorUserId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE communication_email_template_variants
             SET subject_template = :subject_template,
                 html_template = :html_template,
                 text_template = :text_template,
                 updated_by_user_id = :updated_by_user_id,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'subject_template' => $data['subject_template'],
            'html_template' => $data['html_template'],
            'text_template' => $data['text_template'],
            'updated_by_user_id' => $actorUserId,
        ]);
    }

    public function setVariantStatus(int $id, string $status, int $actorUserId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE communication_email_template_variants
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

    /** @return array<int,array<string,mixed>> */
    public function revisionsForKey(string $key, string $locale, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $statement = Database::connection()->prepare(
            'SELECT r.*, u.name AS created_by_name
             FROM communication_email_template_revisions r
             INNER JOIN communication_email_templates t ON t.id = r.template_id
             LEFT JOIN users u ON u.id = r.created_by_user_id
             WHERE t.template_key = :template_key AND r.locale = :locale
             ORDER BY r.revision_number DESC, r.id DESC
             LIMIT ' . $limit
        );
        $statement->execute([
            'template_key' => strtolower(trim($key)),
            'locale' => strtolower(trim($locale)),
        ]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function revisionById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT r.*, t.template_key
             FROM communication_email_template_revisions r
             INNER JOIN communication_email_templates t ON t.id = r.template_id
             WHERE r.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $template */
    public function snapshotBase(array $template, int $actorUserId, string $event): int
    {
        return $this->addRevision(
            (int) $template['id'],
            'en',
            (string) ($template['name'] ?? ''),
            isset($template['description']) ? (string) $template['description'] : null,
            (string) ($template['subject_template'] ?? ''),
            (string) ($template['html_template'] ?? ''),
            (string) ($template['text_template'] ?? ''),
            (string) ($template['status'] ?? 'inactive'),
            $actorUserId,
            $event
        );
    }

    /** @param array<string,mixed> $variant */
    public function snapshotVariant(array $variant, int $actorUserId, string $event): int
    {
        return $this->addRevision(
            (int) $variant['template_id'],
            (string) $variant['locale'],
            null,
            null,
            (string) ($variant['subject_template'] ?? ''),
            (string) ($variant['html_template'] ?? ''),
            (string) ($variant['text_template'] ?? ''),
            (string) ($variant['status'] ?? 'inactive'),
            $actorUserId,
            $event
        );
    }

    private function addRevision(
        int $templateId,
        string $locale,
        ?string $name,
        ?string $description,
        string $subject,
        string $html,
        string $text,
        string $status,
        int $actorUserId,
        string $event
    ): int {
        $pdo = Database::connection();
        $counter = $pdo->prepare(
            'SELECT COALESCE(MAX(revision_number), 0) + 1
             FROM communication_email_template_revisions
             WHERE template_id = :template_id AND locale = :locale'
        );
        $counter->execute(['template_id' => $templateId, 'locale' => $locale]);
        $number = max(1, (int) $counter->fetchColumn());

        $statement = $pdo->prepare(
            'INSERT INTO communication_email_template_revisions
                (uuid, template_id, locale, revision_number, revision_event,
                 name, description, subject_template, html_template, text_template,
                 status, created_by_user_id)
             VALUES
                (:uuid, :template_id, :locale, :revision_number, :revision_event,
                 :name, :description, :subject_template, :html_template, :text_template,
                 :status, :created_by_user_id)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'template_id' => $templateId,
            'locale' => $locale,
            'revision_number' => $number,
            'revision_event' => mb_substr($event, 0, 40),
            'name' => $name,
            'description' => $description,
            'subject_template' => $subject,
            'html_template' => $html,
            'text_template' => $text,
            'status' => $status,
            'created_by_user_id' => $actorUserId,
        ]);
        return (int) $pdo->lastInsertId();
    }
}
