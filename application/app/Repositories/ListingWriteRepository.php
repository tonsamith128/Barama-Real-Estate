<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use Barama\Support\IdGenerator;
use PDO;

final class ListingWriteRepository
{
    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO listings
                (uuid, property_id, created_by_user_id, slug, transaction_type,
                 status, price, currency, pricing_period, deposit_amount,
                 negotiable, available_from, expires_at)
             VALUES
                (:uuid, :property_id, :created_by_user_id, :slug, :transaction_type,
                 :status, :price, :currency, :pricing_period, :deposit_amount,
                 :negotiable, :available_from, :expires_at)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'property_id' => $data['property_id'],
            'created_by_user_id' => $data['created_by_user_id'],
            'slug' => $data['slug'],
            'transaction_type' => $data['transaction_type'],
            'status' => 'draft',
            'price' => $data['price'],
            'currency' => $data['currency'],
            'pricing_period' => $data['pricing_period'],
            'deposit_amount' => $data['deposit_amount'],
            'negotiable' => $data['negotiable'] ? 1 : 0,
            'available_from' => $data['available_from'],
            'expires_at' => $data['expires_at'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE listings
             SET transaction_type = :transaction_type,
                 price = :price,
                 currency = :currency,
                 pricing_period = :pricing_period,
                 deposit_amount = :deposit_amount,
                 negotiable = :negotiable,
                 available_from = :available_from,
                 expires_at = :expires_at,
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
            'SELECT listings.*, properties.company_id, properties.status AS property_status
             FROM listings
             INNER JOIN properties ON properties.id = listings.property_id
             WHERE listings.id = :id
               AND listings.deleted_at IS NULL
               AND properties.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $listing = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($listing) ? $listing : null;
    }

    public function slugExists(string $slug): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM listings WHERE slug = :slug LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);

        return $statement->fetchColumn() !== false;
    }
}
