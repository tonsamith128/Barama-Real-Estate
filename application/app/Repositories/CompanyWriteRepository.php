<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use Barama\Support\IdGenerator;

final class CompanyWriteRepository
{
    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO companies
                (uuid, location_id, name, slug, legal_name, company_type,
                 registration_number, tax_number, email, phone, website,
                 address_line, description, status)
             VALUES
                (:uuid, :location_id, :name, :slug, :legal_name, :company_type,
                 :registration_number, :tax_number, :email, :phone, :website,
                 :address_line, :description, :status)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'location_id' => $data['location_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'legal_name' => $data['legal_name'],
            'company_type' => $data['company_type'],
            'registration_number' => $data['registration_number'],
            'tax_number' => $data['tax_number'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'website' => $data['website'],
            'address_line' => $data['address_line'],
            'description' => $data['description'],
            'status' => 'draft',
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $companyId, array $data): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE companies
             SET location_id = :location_id,
                 name = :name,
                 legal_name = :legal_name,
                 company_type = :company_type,
                 registration_number = :registration_number,
                 tax_number = :tax_number,
                 email = :email,
                 phone = :phone,
                 website = :website,
                 address_line = :address_line,
                 description = :description,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $statement->execute(array_merge($data, ['id' => $companyId]));
    }

    public function slugExists(string $slug): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM companies WHERE slug = :slug LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);

        return $statement->fetchColumn() !== false;
    }
}
