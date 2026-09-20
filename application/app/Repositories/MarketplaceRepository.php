<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use InvalidArgumentException;
use PDO;

final class MarketplaceRepository
{
    /**
     * @param array<string, mixed> $filters
     * @return array{rows:array<int,array<string,mixed>>,total:int,page:int,pages:int,per_page:int}
     */
    public function listings(array $filters, int $page, int $perPage = 18): array
    {
        $where = [
            'l.status = "published"',
            'l.published_at IS NOT NULL',
            'l.deleted_at IS NULL',
            '(l.expires_at IS NULL OR l.expires_at > CURRENT_TIMESTAMP)',
            'p.status = "approved"',
            'p.deleted_at IS NULL',
            'c.status = "approved"',
            'c.published_at IS NOT NULL',
            'c.deleted_at IS NULL',
        ];
        $params = [];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(p.title LIKE :q_title OR p.reference_code LIKE :q_ref OR c.name LIKE :q_company OR loc.name LIKE :q_location)';
            $needle = '%' . $query . '%';
            $params['q_title'] = $needle;
            $params['q_ref'] = $needle;
            $params['q_company'] = $needle;
            $params['q_location'] = $needle;
        }

        $offer = trim((string) ($filters['offer'] ?? ''));
        if (in_array($offer, ['sale', 'rent'], true)) {
            $where[] = 'l.transaction_type = :offer';
            $params['offer'] = $offer;
        }

        $propertyType = trim((string) ($filters['type'] ?? ''));
        if ($propertyType !== '') {
            $where[] = 'p.property_type = :property_type';
            $params['property_type'] = $propertyType;
        }

        $locationId = filter_var($filters['location'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($locationId !== false && $locationId !== null) {
            $where[] = 'p.location_id = :location_id';
            $params['location_id'] = (int) $locationId;
        }

        $bedrooms = filter_var($filters['bedrooms'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($bedrooms !== false && $bedrooms !== null && $bedrooms >= 0) {
            $where[] = 'p.bedrooms >= :bedrooms';
            $params['bedrooms'] = (float) $bedrooms;
        }

        $minPrice = filter_var($filters['min_price'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($minPrice !== false && $minPrice !== null && $minPrice >= 0) {
            $where[] = 'l.price >= :min_price';
            $params['min_price'] = (float) $minPrice;
        }

        $maxPrice = filter_var($filters['max_price'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($maxPrice !== false && $maxPrice !== null && $maxPrice >= 0) {
            $where[] = 'l.price <= :max_price';
            $params['max_price'] = (float) $maxPrice;
        }

        $currency = strtoupper(trim((string) ($filters['currency'] ?? '')));
        if (preg_match('/^[A-Z]{3}$/', $currency) === 1) {
            $where[] = 'l.currency = :currency';
            $params['currency'] = $currency;
        }

        $sort = trim((string) ($filters['sort'] ?? 'newest'));
        $orderBy = match ($sort) {
            'price_asc' => 'l.price ASC, l.published_at DESC',
            'price_desc' => 'l.price DESC, l.published_at DESC',
            'oldest' => 'l.published_at ASC, l.id ASC',
            default => 'l.published_at DESC, l.id DESC',
        };

        $from = ' FROM listings l
                  INNER JOIN properties p ON p.id = l.property_id
                  INNER JOIN companies c ON c.id = p.company_id
                  LEFT JOIN projects pr ON pr.id = p.project_id AND pr.deleted_at IS NULL
                  LEFT JOIN locations loc ON loc.id = p.location_id';

        $select = 'SELECT l.id, l.slug, l.transaction_type, l.price, l.currency,
                          l.pricing_period, l.negotiable, l.published_at,
                          p.id AS property_id, p.reference_code, p.title,
                          p.property_type, p.bedrooms, p.bathrooms,
                          p.area_value, p.area_unit, p.address_line,
                          c.name AS company_name, c.slug AS company_slug,
                          pr.name AS project_name, pr.slug AS project_slug,
                          loc.name AS location_name,
                          (
                              SELECT ma2.media_asset_id
                              FROM media_attachments ma2
                              INNER JOIN media_assets m2 ON m2.id = ma2.media_asset_id
                              WHERE ma2.entity_type = "listing"
                                AND ma2.entity_id = l.id
                                AND m2.mime_type LIKE "image/%"
                              ORDER BY ma2.is_primary DESC, ma2.position ASC, ma2.id ASC
                              LIMIT 1
                          ) AS primary_media_id,
                          (
                              SELECT m3.uuid
                              FROM media_attachments ma3
                              INNER JOIN media_assets m3 ON m3.id = ma3.media_asset_id
                              WHERE ma3.entity_type = "listing"
                                AND ma3.entity_id = l.id
                                AND m3.mime_type LIKE "image/%"
                              ORDER BY ma3.is_primary DESC, ma3.position ASC, ma3.id ASC
                              LIMIT 1
                          ) AS primary_media_uuid,
                          (
                              SELECT m4.alt_text
                              FROM media_attachments ma4
                              INNER JOIN media_assets m4 ON m4.id = ma4.media_asset_id
                              WHERE ma4.entity_type = "listing"
                                AND ma4.entity_id = l.id
                                AND m4.mime_type LIKE "image/%"
                              ORDER BY ma4.is_primary DESC, ma4.position ASC, ma4.id ASC
                              LIMIT 1
                          ) AS primary_media_alt';

        return $this->paginate(
            $select . $from . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderBy,
            'SELECT COUNT(*)' . $from . ' WHERE ' . implode(' AND ', $where),
            $params,
            $page,
            $perPage
        );
    }

    /** @return array<string,mixed>|null */
    public function listingBySlug(string $slug): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT l.*,
                    p.id AS property_id, p.reference_code, p.title,
                    p.property_type, p.bedrooms, p.bathrooms, p.parking_spaces,
                    p.floor_number, p.total_floors, p.area_value, p.area_unit,
                    p.land_area_value, p.land_area_unit, p.furnishing_status,
                    p.year_built, p.address_line, p.description AS property_description,
                    c.id AS company_id, c.name AS company_name, c.slug AS company_slug,
                    c.phone AS company_phone, c.email AS company_email,
                    pr.id AS project_id, pr.name AS project_name, pr.slug AS project_slug,
                    loc.id AS location_id, loc.name AS location_name,
                    loc.latitude AS location_latitude, loc.longitude AS location_longitude
             FROM listings l
             INNER JOIN properties p ON p.id = l.property_id
             INNER JOIN companies c ON c.id = p.company_id
             LEFT JOIN projects pr ON pr.id = p.project_id AND pr.deleted_at IS NULL
             LEFT JOIN locations loc ON loc.id = p.location_id
             WHERE l.slug = :slug
               AND l.status = "published"
               AND l.published_at IS NOT NULL
               AND l.deleted_at IS NULL
               AND (l.expires_at IS NULL OR l.expires_at > CURRENT_TIMESTAMP)
               AND p.status = "approved"
               AND p.deleted_at IS NULL
               AND c.status = "approved"
               AND c.published_at IS NOT NULL
               AND c.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['slug' => trim($slug)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{rows:array<int,array<string,mixed>>,total:int,page:int,pages:int,per_page:int}
     */
    public function projects(array $filters, int $page, int $perPage = 16): array
    {
        $where = [
            'p.status = "published"',
            'p.published_at IS NOT NULL',
            'p.deleted_at IS NULL',
            'c.status = "approved"',
            'c.published_at IS NOT NULL',
            'c.deleted_at IS NULL',
        ];
        $params = [];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(p.name LIKE :q_name OR c.name LIKE :q_company OR loc.name LIKE :q_location)';
            $needle = '%' . $query . '%';
            $params['q_name'] = $needle;
            $params['q_company'] = $needle;
            $params['q_location'] = $needle;
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $where[] = 'p.project_type = :project_type';
            $params['project_type'] = $type;
        }

        $completion = trim((string) ($filters['completion'] ?? ''));
        if ($completion !== '') {
            $where[] = 'p.completion_status = :completion';
            $params['completion'] = $completion;
        }

        $locationId = filter_var($filters['location'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($locationId !== false && $locationId !== null) {
            $where[] = 'p.location_id = :location_id';
            $params['location_id'] = (int) $locationId;
        }

        $from = ' FROM projects p
                  INNER JOIN companies c ON c.id = p.company_id
                  LEFT JOIN locations loc ON loc.id = p.location_id';

        $select = 'SELECT p.id, p.name, p.slug, p.project_type,
                          p.completion_status, p.address_line, p.description,
                          p.expected_completion_date, p.published_at,
                          c.name AS company_name, c.slug AS company_slug,
                          loc.name AS location_name,
                          (
                              SELECT m.uuid
                              FROM media_attachments ma
                              INNER JOIN media_assets m ON m.id = ma.media_asset_id
                              WHERE ma.entity_type = "project"
                                AND ma.entity_id = p.id
                                AND m.mime_type LIKE "image/%"
                              ORDER BY ma.is_primary DESC, ma.position ASC, ma.id ASC
                              LIMIT 1
                          ) AS primary_media_uuid,
                          (
                              SELECT m.alt_text
                              FROM media_attachments ma
                              INNER JOIN media_assets m ON m.id = ma.media_asset_id
                              WHERE ma.entity_type = "project"
                                AND ma.entity_id = p.id
                                AND m.mime_type LIKE "image/%"
                              ORDER BY ma.is_primary DESC, ma.position ASC, ma.id ASC
                              LIMIT 1
                          ) AS primary_media_alt';

        return $this->paginate(
            $select . $from . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY p.published_at DESC, p.id DESC',
            'SELECT COUNT(*)' . $from . ' WHERE ' . implode(' AND ', $where),
            $params,
            $page,
            $perPage
        );
    }

    /** @return array<string,mixed>|null */
    public function projectBySlug(string $slug): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT p.*, c.id AS company_id, c.name AS company_name,
                    c.slug AS company_slug, c.phone AS company_phone,
                    c.email AS company_email, loc.name AS location_name
             FROM projects p
             INNER JOIN companies c ON c.id = p.company_id
             LEFT JOIN locations loc ON loc.id = p.location_id
             WHERE p.slug = :slug
               AND p.status = "published"
               AND p.published_at IS NOT NULL
               AND p.deleted_at IS NULL
               AND c.status = "approved"
               AND c.published_at IS NOT NULL
               AND c.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['slug' => trim($slug)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{rows:array<int,array<string,mixed>>,total:int,page:int,pages:int,per_page:int}
     */
    public function companies(array $filters, int $page, int $perPage = 16): array
    {
        $where = [
            'c.status = "approved"',
            'c.published_at IS NOT NULL',
            'c.deleted_at IS NULL',
        ];
        $params = [];

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(c.name LIKE :q_name OR c.description LIKE :q_description OR loc.name LIKE :q_location)';
            $needle = '%' . $query . '%';
            $params['q_name'] = $needle;
            $params['q_description'] = $needle;
            $params['q_location'] = $needle;
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $where[] = 'c.company_type = :company_type';
            $params['company_type'] = $type;
        }

        $locationId = filter_var($filters['location'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($locationId !== false && $locationId !== null) {
            $where[] = 'c.location_id = :location_id';
            $params['location_id'] = (int) $locationId;
        }

        $from = ' FROM companies c LEFT JOIN locations loc ON loc.id = c.location_id';

        $select = 'SELECT c.id, c.name, c.slug, c.company_type, c.description,
                          c.website, c.phone, c.verified_at, c.published_at,
                          loc.name AS location_name,
                          (SELECT COUNT(*) FROM projects p
                           WHERE p.company_id = c.id
                             AND p.status = "published"
                             AND p.published_at IS NOT NULL
                             AND p.deleted_at IS NULL) AS project_count,
                          (SELECT COUNT(*)
                           FROM listings l
                           INNER JOIN properties pr ON pr.id = l.property_id
                           WHERE pr.company_id = c.id
                             AND pr.status = "approved"
                             AND pr.deleted_at IS NULL
                             AND l.status = "published"
                             AND l.published_at IS NOT NULL
                             AND l.deleted_at IS NULL
                             AND (l.expires_at IS NULL OR l.expires_at > CURRENT_TIMESTAMP)) AS listing_count,
                          (
                              SELECT m.uuid
                              FROM media_attachments ma
                              INNER JOIN media_assets m ON m.id = ma.media_asset_id
                              WHERE ma.entity_type = "company"
                                AND ma.entity_id = c.id
                                AND m.mime_type LIKE "image/%"
                              ORDER BY ma.is_primary DESC, ma.position ASC, ma.id ASC
                              LIMIT 1
                          ) AS primary_media_uuid,
                          (
                              SELECT m.alt_text
                              FROM media_attachments ma
                              INNER JOIN media_assets m ON m.id = ma.media_asset_id
                              WHERE ma.entity_type = "company"
                                AND ma.entity_id = c.id
                                AND m.mime_type LIKE "image/%"
                              ORDER BY ma.is_primary DESC, ma.position ASC, ma.id ASC
                              LIMIT 1
                          ) AS primary_media_alt';

        return $this->paginate(
            $select . $from . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY c.verified_at DESC, c.name ASC',
            'SELECT COUNT(*)' . $from . ' WHERE ' . implode(' AND ', $where),
            $params,
            $page,
            $perPage
        );
    }

    /** @return array<string,mixed>|null */
    public function companyBySlug(string $slug): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT c.*, loc.name AS location_name
             FROM companies c
             LEFT JOIN locations loc ON loc.id = c.location_id
             WHERE c.slug = :slug
               AND c.status = "approved"
               AND c.published_at IS NOT NULL
               AND c.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['slug' => trim($slug)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<int,array<string,mixed>> */
    public function companyProjects(int $companyId, int $limit = 6): array
    {
        $statement = Database::connection()->prepare(
            'SELECT p.id, p.name, p.slug, p.project_type, p.completion_status,
                    p.address_line, loc.name AS location_name,
                    (
                        SELECT m.uuid
                        FROM media_attachments ma
                        INNER JOIN media_assets m ON m.id = ma.media_asset_id
                        WHERE ma.entity_type = "project"
                          AND ma.entity_id = p.id
                          AND m.mime_type LIKE "image/%"
                        ORDER BY ma.is_primary DESC, ma.position ASC, ma.id ASC
                        LIMIT 1
                    ) AS primary_media_uuid
             FROM projects p
             LEFT JOIN locations loc ON loc.id = p.location_id
             WHERE p.company_id = :company_id
               AND p.status = "published"
               AND p.published_at IS NOT NULL
               AND p.deleted_at IS NULL
             ORDER BY p.published_at DESC
             LIMIT :limit'
        );
        $statement->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $statement->bindValue(':limit', max(1, min(24, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function projectListings(int $projectId, int $limit = 6): array
    {
        $statement = Database::connection()->prepare(
            'SELECT l.id, l.slug, l.transaction_type, l.price, l.currency,
                    l.pricing_period, p.title, p.property_type,
                    p.bedrooms, p.bathrooms, loc.name AS location_name,
                    (
                        SELECT m.uuid
                        FROM media_attachments ma
                        INNER JOIN media_assets m ON m.id = ma.media_asset_id
                        WHERE ma.entity_type = "listing"
                          AND ma.entity_id = l.id
                          AND m.mime_type LIKE "image/%"
                        ORDER BY ma.is_primary DESC, ma.position ASC, ma.id ASC
                        LIMIT 1
                    ) AS primary_media_uuid
             FROM listings l
             INNER JOIN properties p ON p.id = l.property_id
             INNER JOIN companies c ON c.id = p.company_id
             LEFT JOIN locations loc ON loc.id = p.location_id
             WHERE p.project_id = :project_id
               AND p.status = "approved"
               AND p.deleted_at IS NULL
               AND c.status = "approved"
               AND c.published_at IS NOT NULL
               AND c.deleted_at IS NULL
               AND l.status = "published"
               AND l.published_at IS NOT NULL
               AND l.deleted_at IS NULL
               AND (l.expires_at IS NULL OR l.expires_at > CURRENT_TIMESTAMP)
             ORDER BY l.published_at DESC
             LIMIT :limit'
        );
        $statement->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $statement->bindValue(':limit', max(1, min(24, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function companyListings(int $companyId, int $limit = 6): array
    {
        $statement = Database::connection()->prepare(
            'SELECT l.id, l.slug, l.transaction_type, l.price, l.currency,
                    l.pricing_period, p.title, p.property_type,
                    p.bedrooms, p.bathrooms, loc.name AS location_name,
                    (
                        SELECT m.uuid
                        FROM media_attachments ma
                        INNER JOIN media_assets m ON m.id = ma.media_asset_id
                        WHERE ma.entity_type = "listing"
                          AND ma.entity_id = l.id
                          AND m.mime_type LIKE "image/%"
                        ORDER BY ma.is_primary DESC, ma.position ASC, ma.id ASC
                        LIMIT 1
                    ) AS primary_media_uuid
             FROM listings l
             INNER JOIN properties p ON p.id = l.property_id
             LEFT JOIN locations loc ON loc.id = p.location_id
             WHERE p.company_id = :company_id
               AND p.status = "approved"
               AND p.deleted_at IS NULL
               AND l.status = "published"
               AND l.published_at IS NOT NULL
               AND l.deleted_at IS NULL
               AND (l.expires_at IS NULL OR l.expires_at > CURRENT_TIMESTAMP)
             ORDER BY l.published_at DESC
             LIMIT :limit'
        );
        $statement->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $statement->bindValue(':limit', max(1, min(24, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function gallery(string $entityType, int $entityId): array
    {
        if (!in_array($entityType, ['company', 'project', 'property', 'listing'], true)) {
            throw new InvalidArgumentException('Unsupported media entity type.');
        }

        $statement = Database::connection()->prepare(
            'SELECT ma.id AS attachment_id, ma.collection, ma.position, ma.is_primary,
                    m.id AS media_id, m.uuid, m.original_name, m.mime_type,
                    m.width_px, m.height_px, m.alt_text
             FROM media_attachments ma
             INNER JOIN media_assets m ON m.id = ma.media_asset_id
             WHERE ma.entity_type = :entity_type
               AND ma.entity_id = :entity_id
               AND m.mime_type LIKE "image/%"
             ORDER BY ma.is_primary DESC, ma.position ASC, ma.id ASC'
        );
        $statement->execute([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function publicMedia(string $uuid): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT m.*
             FROM media_assets m
             WHERE m.uuid = :uuid
               AND m.mime_type IN ("image/jpeg", "image/png", "image/webp")
               AND EXISTS (
                   SELECT 1
                   FROM media_attachments ma
                   WHERE ma.media_asset_id = m.id
                     AND (
                         (
                             ma.entity_type = "company"
                             AND EXISTS (
                                 SELECT 1 FROM companies c
                                 WHERE c.id = ma.entity_id
                                   AND c.status = "approved"
                                   AND c.published_at IS NOT NULL
                                   AND c.deleted_at IS NULL
                             )
                         )
                         OR (
                             ma.entity_type = "project"
                             AND EXISTS (
                                 SELECT 1
                                 FROM projects p
                                 INNER JOIN companies c ON c.id = p.company_id
                                 WHERE p.id = ma.entity_id
                                   AND p.status = "published"
                                   AND p.published_at IS NOT NULL
                                   AND p.deleted_at IS NULL
                                   AND c.status = "approved"
                                   AND c.published_at IS NOT NULL
                                   AND c.deleted_at IS NULL
                             )
                         )
                         OR (
                             ma.entity_type = "property"
                             AND EXISTS (
                                 SELECT 1
                                 FROM properties p
                                 INNER JOIN companies c ON c.id = p.company_id
                                 WHERE p.id = ma.entity_id
                                   AND p.status = "approved"
                                   AND p.deleted_at IS NULL
                                   AND c.status = "approved"
                                   AND c.published_at IS NOT NULL
                                   AND c.deleted_at IS NULL
                             )
                         )
                         OR (
                             ma.entity_type = "listing"
                             AND EXISTS (
                                 SELECT 1
                                 FROM listings l
                                 INNER JOIN properties p ON p.id = l.property_id
                                 INNER JOIN companies c ON c.id = p.company_id
                                 WHERE l.id = ma.entity_id
                                   AND l.status = "published"
                                   AND l.published_at IS NOT NULL
                                   AND l.deleted_at IS NULL
                                   AND (l.expires_at IS NULL OR l.expires_at > CURRENT_TIMESTAMP)
                                   AND p.status = "approved"
                                   AND p.deleted_at IS NULL
                                   AND c.status = "approved"
                                   AND c.published_at IS NOT NULL
                                   AND c.deleted_at IS NULL
                             )
                         )
                     )
               )
             LIMIT 1'
        );
        $statement->execute(['uuid' => trim($uuid)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return array<int,array<string,mixed>> */
    public function activeLocations(): array
    {
        return Database::connection()
            ->query(
                'SELECT id, name, type
                 FROM locations
                 WHERE status = "active"
                 ORDER BY sort_order ASC, name ASC
                 LIMIT 500'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string,int|float|string> $params
     * @return array{rows:array<int,array<string,mixed>>,total:int,page:int,pages:int,per_page:int}
     */
    private function paginate(
        string $sql,
        string $countSql,
        array $params,
        int $page,
        int $perPage
    ): array {
        $perPage = max(1, min(48, $perPage));
        $page = max(1, $page);

        $countStatement = Database::connection()->prepare($countSql);
        $this->bind($countStatement, $params);
        $countStatement->execute();
        $total = (int) $countStatement->fetchColumn();

        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $statement = Database::connection()->prepare($sql . ' LIMIT :limit OFFSET :offset');
        $this->bind($statement, $params);
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return [
            'rows' => $statement->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    /** @param array<string,int|float|string> $params */
    private function bind(\PDOStatement $statement, array $params): void
    {
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $statement->bindValue(':' . $key, $value, PDO::PARAM_INT);
                continue;
            }

            $statement->bindValue(':' . $key, (string) $value, PDO::PARAM_STR);
        }
    }
}
