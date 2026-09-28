<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use Barama\Support\IdGenerator;
use PDO;

final class PropertyWriteRepository
{
    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO properties
                (uuid, company_id, owner_user_id, project_id, location_id,
                 reference_code, title, slug, property_type, status,
                 bedrooms, bathrooms, parking_spaces, floor_number, total_floors,
                 area_value, area_unit, land_area_value, land_area_unit,
                 furnishing_status, year_built, address_line, description)
             VALUES
                (:uuid, :company_id, :owner_user_id, :project_id, :location_id,
                 :reference_code, :title, :slug, :property_type, :status,
                 :bedrooms, :bathrooms, :parking_spaces, :floor_number, :total_floors,
                 :area_value, :area_unit, :land_area_value, :land_area_unit,
                 :furnishing_status, :year_built, :address_line, :description)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'company_id' => $data['company_id'],
            'owner_user_id' => $data['owner_user_id'],
            'project_id' => $data['project_id'],
            'location_id' => $data['location_id'],
            'reference_code' => $data['reference_code'],
            'title' => $data['title'],
            'slug' => $data['slug'],
            'property_type' => $data['property_type'],
            'status' => 'draft',
            'bedrooms' => $data['bedrooms'],
            'bathrooms' => $data['bathrooms'],
            'parking_spaces' => $data['parking_spaces'],
            'floor_number' => $data['floor_number'],
            'total_floors' => $data['total_floors'],
            'area_value' => $data['area_value'],
            'area_unit' => $data['area_unit'],
            'land_area_value' => $data['land_area_value'],
            'land_area_unit' => $data['land_area_unit'],
            'furnishing_status' => $data['furnishing_status'],
            'year_built' => $data['year_built'],
            'address_line' => $data['address_line'],
            'description' => $data['description'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE properties
             SET project_id = :project_id,
                 location_id = :location_id,
                 title = :title,
                 property_type = :property_type,
                 bedrooms = :bedrooms,
                 bathrooms = :bathrooms,
                 parking_spaces = :parking_spaces,
                 floor_number = :floor_number,
                 total_floors = :total_floors,
                 area_value = :area_value,
                 area_unit = :area_unit,
                 land_area_value = :land_area_value,
                 land_area_unit = :land_area_unit,
                 furnishing_status = :furnishing_status,
                 year_built = :year_built,
                 address_line = :address_line,
                 description = :description,
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
            'SELECT * FROM properties WHERE id = :id AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $property = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($property) ? $property : null;
    }

    public function slugExists(string $slug): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM properties WHERE slug = :slug LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);

        return $statement->fetchColumn() !== false;
    }

    public function activeListingCount(int $propertyId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM listings
             WHERE property_id = :property_id
               AND deleted_at IS NULL'
        );
        $statement->execute(['property_id' => $propertyId]);

        return (int) $statement->fetchColumn();
    }

    public function softDelete(int $propertyId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE properties
             SET deleted_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $statement->execute(['id' => $propertyId]);
    }
}
