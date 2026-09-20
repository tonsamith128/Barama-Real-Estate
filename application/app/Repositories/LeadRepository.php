<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use Barama\Support\IdGenerator;

final class LeadRepository
{
    public function inquiryAlreadyConverted(int $inquiryId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM leads WHERE inquiry_id = :inquiry_id LIMIT 1'
        );
        $statement->execute(['inquiry_id' => $inquiryId]);

        return $statement->fetchColumn() !== false;
    }

    /** @param array<string, mixed> $data */
    public function createFromInquiry(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO leads
                (uuid, company_id, inquiry_id, assigned_user_id, contact_name,
                 contact_email, contact_phone, source, stage, transaction_type,
                 property_type, preferred_location_id, budget_min, budget_max,
                 currency, notes)
             VALUES
                (:uuid, :company_id, :inquiry_id, :assigned_user_id, :contact_name,
                 :contact_email, :contact_phone, :source, :stage, :transaction_type,
                 :property_type, :preferred_location_id, :budget_min, :budget_max,
                 :currency, :notes)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'company_id' => $data['company_id'],
            'inquiry_id' => $data['inquiry_id'],
            'assigned_user_id' => $data['assigned_user_id'],
            'contact_name' => $data['contact_name'],
            'contact_email' => $data['contact_email'],
            'contact_phone' => $data['contact_phone'],
            'source' => 'inquiry',
            'stage' => 'new',
            'transaction_type' => $data['transaction_type'],
            'property_type' => $data['property_type'],
            'preferred_location_id' => $data['preferred_location_id'],
            'budget_min' => $data['budget_min'],
            'budget_max' => $data['budget_max'],
            'currency' => $data['currency'],
            'notes' => $data['notes'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }
}
