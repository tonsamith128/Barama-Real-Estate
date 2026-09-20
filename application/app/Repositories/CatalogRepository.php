<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use Barama\Support\IdGenerator;

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

    public function amenitySlugExists(string $slug): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM amenities WHERE slug = :slug LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);

        return $statement->fetchColumn() !== false;
    }
}
