<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use Barama\Support\IdGenerator;
use PDO;

final class CatalogRepository
{
    /** @param array<string, mixed> $data */
    public function createLocation(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO locations
                (uuid, parent_id, type, country_code, name, slug, latitude, longitude, status, sort_order)
             VALUES
                (:uuid, :parent_id, :type, :country_code, :name, :slug, :latitude, :longitude, :status, :sort_order)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'parent_id' => $data['parent_id'],
            'type' => $data['type'],
            'country_code' => $data['country_code'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'status' => 'active',
            'sort_order' => $data['sort_order'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function locationSlugExists(string $slug): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM locations WHERE slug = :slug LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);

        return $statement->fetchColumn() !== false;
    }

    public function locationExists(int $id): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM locations WHERE id = :id AND status = :status LIMIT 1'
        );
        $statement->execute(['id' => $id, 'status' => 'active']);

        return $statement->fetchColumn() !== false;
    }

    /** @return array<string,mixed>|null */
    public function location(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, parent_id, type, name, status
             FROM locations
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string,int> */
    public function locationUsage(int $id): array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                (SELECT COUNT(*) FROM locations child
                 WHERE child.parent_id = :location_id_children
                   AND child.status = "active") AS active_children,
                (SELECT COUNT(*) FROM companies c
                 WHERE c.location_id = :location_id_companies
                   AND c.deleted_at IS NULL) AS companies,
                (SELECT COUNT(*) FROM projects p
                 WHERE p.location_id = :location_id_projects
                   AND p.deleted_at IS NULL) AS projects,
                (SELECT COUNT(*) FROM properties p
                 WHERE p.location_id = :location_id_properties
                   AND p.deleted_at IS NULL) AS properties,
                (SELECT COUNT(*) FROM leads l
                 WHERE l.preferred_location_id = :location_id_leads
                   AND l.deleted_at IS NULL) AS leads,
                (SELECT COUNT(*) FROM saved_searches s
                 WHERE s.location_id = :location_id_searches) AS saved_searches'
        );
        $statement->execute([
            'location_id_children' => $id,
            'location_id_companies' => $id,
            'location_id_projects' => $id,
            'location_id_properties' => $id,
            'location_id_leads' => $id,
            'location_id_searches' => $id,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'active_children' => (int) ($row['active_children'] ?? 0),
            'companies' => (int) ($row['companies'] ?? 0),
            'projects' => (int) ($row['projects'] ?? 0),
            'properties' => (int) ($row['properties'] ?? 0),
            'leads' => (int) ($row['leads'] ?? 0),
            'saved_searches' => (int) ($row['saved_searches'] ?? 0),
        ];
    }

    public function setLocationStatus(int $id, string $status): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE locations SET status = :status WHERE id = :id'
        );
        $statement->execute(['status' => $status, 'id' => $id]);
    }

    /** @param array<string, mixed> $data */
    public function createAmenity(array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO amenities
                (uuid, name, slug, category, icon_key, status, sort_order)
             VALUES
                (:uuid, :name, :slug, :category, :icon_key, :status, :sort_order)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'name' => $data['name'],
            'slug' => $data['slug'],
            'category' => $data['category'],
            'icon_key' => $data['icon_key'],
            'status' => 'active',
            'sort_order' => $data['sort_order'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    /** @return array<string,mixed>|null */
    public function amenity(int $id): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT id, name, category, status
             FROM amenities
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<string,int> */
    public function amenityUsage(int $id): array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                (SELECT COUNT(*)
                 FROM project_amenities pa
                 INNER JOIN projects p ON p.id = pa.project_id
                 WHERE pa.amenity_id = :amenity_id_projects
                   AND p.deleted_at IS NULL) AS projects,
                (SELECT COUNT(*)
                 FROM property_amenities pa
                 INNER JOIN properties p ON p.id = pa.property_id
                 WHERE pa.amenity_id = :amenity_id_properties
                   AND p.deleted_at IS NULL) AS properties'
        );
        $statement->execute([
            'amenity_id_projects' => $id,
            'amenity_id_properties' => $id,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'projects' => (int) ($row['projects'] ?? 0),
            'properties' => (int) ($row['properties'] ?? 0),
        ];
    }

    public function setAmenityStatus(int $id, string $status): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE amenities SET status = :status WHERE id = :id'
        );
        $statement->execute(['status' => $status, 'id' => $id]);
    }

    public function amenitySlugExists(string $slug): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM amenities WHERE slug = :slug LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);

        return $statement->fetchColumn() !== false;
    }
}
