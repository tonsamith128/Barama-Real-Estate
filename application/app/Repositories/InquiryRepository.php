<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use Barama\Support\IdGenerator;
use PDO;

final class InquiryRepository
{
    /** @return array<string, mixed>|null */
    public function resolvePublicTarget(string $type, int $id): ?array
    {
        $sql = match ($type) {
            'listing' => 'SELECT properties.company_id, properties.project_id,
                                 properties.id AS property_id, listings.id AS listing_id
                          FROM listings
                          INNER JOIN properties ON properties.id = listings.property_id
                          LEFT JOIN companies ON companies.id = properties.company_id
                          WHERE listings.id = :id
                            AND listings.status = "published"
                            AND listings.published_at IS NOT NULL
                            AND listings.deleted_at IS NULL
                            AND (listings.expires_at IS NULL OR listings.expires_at > CURRENT_TIMESTAMP)
                            AND properties.status = "approved"
                            AND properties.deleted_at IS NULL
                            AND (
                                properties.company_id IS NULL
                                OR (
                                    companies.status = "approved"
                                    AND companies.published_at IS NOT NULL
                                    AND companies.deleted_at IS NULL
                                )
                            )
                          LIMIT 1',
            'property' => 'SELECT properties.company_id, properties.project_id,
                                  properties.id AS property_id, NULL AS listing_id
                           FROM properties
                           LEFT JOIN companies ON companies.id = properties.company_id
                           WHERE properties.id = :id
                             AND properties.status = "approved"
                             AND properties.deleted_at IS NULL
                             AND (
                                 properties.company_id IS NULL
                                 OR (
                                     companies.status = "approved"
                                     AND companies.published_at IS NOT NULL
                                     AND companies.deleted_at IS NULL
                                 )
                             )
                           LIMIT 1',
            'project' => 'SELECT projects.company_id, projects.id AS project_id,
                                 NULL AS property_id, NULL AS listing_id
                          FROM projects
                          INNER JOIN companies ON companies.id = projects.company_id
                          WHERE projects.id = :id
                            AND projects.status = "published"
                            AND projects.published_at IS NOT NULL
                            AND projects.deleted_at IS NULL
                            AND companies.status = "approved"
                            AND companies.published_at IS NOT NULL
                            AND companies.deleted_at IS NULL
                          LIMIT 1',
            'company' => 'SELECT id AS company_id, NULL AS project_id,
                                 NULL AS property_id, NULL AS listing_id
                          FROM companies
                          WHERE id = :id
                            AND status = "approved"
                            AND published_at IS NOT NULL
                            AND deleted_at IS NULL
                          LIMIT 1',
            default => null,
        };

        if ($sql === null) {
            return null;
        }

        $statement = Database::connection()->prepare($sql);
        $statement->execute(['id' => $id]);
        $target = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($target) ? $target : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO inquiries
                (uuid, company_id, project_id, property_id, listing_id, user_id,
                 contact_name, contact_email, contact_phone, preferred_contact,
                 subject, message, source, status)
             VALUES
                (:uuid, :company_id, :project_id, :property_id, :listing_id, :user_id,
                 :contact_name, :contact_email, :contact_phone, :preferred_contact,
                 :subject, :message, :source, :status)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'company_id' => $data['company_id'],
            'project_id' => $data['project_id'],
            'property_id' => $data['property_id'],
            'listing_id' => $data['listing_id'],
            'user_id' => $data['user_id'],
            'contact_name' => $data['contact_name'],
            'contact_email' => $data['contact_email'],
            'contact_phone' => $data['contact_phone'],
            'preferred_contact' => $data['preferred_contact'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'source' => 'website',
            'status' => 'new',
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT * FROM inquiries WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
