<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use InvalidArgumentException;
use PDO;

final class MarketplaceRepository
{
    /* BARAMA_PUBLIC_LOCATION_FILTER_V112 */
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

        $locationFilter = trim((string) ($filters['location'] ?? ''));


        if (preg_match('/^country:([A-Za-z]{2})$/', $locationFilter, $locationCountry) === 1) {


            $where[] = 'UPPER(loc.country_code) = :location_country';


            $params['location_country'] = strtoupper((string) $locationCountry[1]);


        } else {


            $locationId = filter_var($locationFilter !== '' ? $locationFilter : null, FILTER_VALIDATE_INT, [


                'options' => ['min_range' => 1],


            ]);


            if ($locationId !== false && $locationId !== null) {


                $where[] = 'p.location_id = :location_id';


                $params['location_id'] = (int) $locationId;


            }


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

    /**
     * @return array<int,string>
     */
    public function heroImageUuids(string $page, int $limit = 3): array
    {
        $page = strtolower(trim($page));
        $limit = max(1, min(6, $limit));
        $queryLimit = $limit * 4;

        $sql = match ($page) {
            'properties' => 'SELECT m.uuid
                FROM listings l
                INNER JOIN properties p ON p.id = l.property_id
                INNER JOIN companies c ON c.id = p.company_id
                INNER JOIN media_attachments ma
                    ON ma.entity_type = "listing" AND ma.entity_id = l.id
                INNER JOIN media_assets m ON m.id = ma.media_asset_id
                WHERE l.status = "published"
                  AND l.published_at IS NOT NULL
                  AND l.deleted_at IS NULL
                  AND (l.expires_at IS NULL OR l.expires_at > CURRENT_TIMESTAMP)
                  AND p.status = "approved"
                  AND p.deleted_at IS NULL
                  AND c.status = "approved"
                  AND c.published_at IS NOT NULL
                  AND c.deleted_at IS NULL
                  AND m.mime_type LIKE "image/%"
                ORDER BY l.published_at DESC, ma.is_primary DESC, ma.position ASC, ma.id DESC
                LIMIT :limit',
            'projects' => 'SELECT m.uuid
                FROM projects p
                INNER JOIN companies c ON c.id = p.company_id
                INNER JOIN media_attachments ma
                    ON ma.entity_type = "project" AND ma.entity_id = p.id
                INNER JOIN media_assets m ON m.id = ma.media_asset_id
                WHERE p.status = "published"
                  AND p.published_at IS NOT NULL
                  AND p.deleted_at IS NULL
                  AND c.status = "approved"
                  AND c.published_at IS NOT NULL
                  AND c.deleted_at IS NULL
                  AND m.mime_type LIKE "image/%"
                ORDER BY p.published_at DESC, ma.is_primary DESC, ma.position ASC, ma.id DESC
                LIMIT :limit',
            'companies' => 'SELECT m.uuid
                FROM companies c
                INNER JOIN media_attachments ma
                    ON ma.entity_type = "company" AND ma.entity_id = c.id
                INNER JOIN media_assets m ON m.id = ma.media_asset_id
                WHERE c.status = "approved"
                  AND c.published_at IS NOT NULL
                  AND c.deleted_at IS NULL
                  AND m.mime_type LIKE "image/%"
                ORDER BY c.verified_at DESC, c.published_at DESC, ma.is_primary DESC, ma.position ASC, ma.id DESC
                LIMIT :limit',
            default => throw new InvalidArgumentException('Unsupported marketplace hero image page.'),
        };

        $statement = Database::connection()->prepare($sql);
        $statement->bindValue(':limit', $queryLimit, PDO::PARAM_INT);
        $statement->execute();

        $uuids = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $uuid) {
            $uuid = trim((string) $uuid);
            if ($uuid === '' || in_array($uuid, $uuids, true)) {
                continue;
            }

            $uuids[] = $uuid;
            if (count($uuids) >= $limit) {
                break;
            }
        }

        return $uuids;
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

        $locationFilter = trim((string) ($filters['location'] ?? ''));


        if (preg_match('/^country:([A-Za-z]{2})$/', $locationFilter, $locationCountry) === 1) {


            $where[] = 'UPPER(loc.country_code) = :location_country';


            $params['location_country'] = strtoupper((string) $locationCountry[1]);


        } else {


            $locationId = filter_var($locationFilter !== '' ? $locationFilter : null, FILTER_VALIDATE_INT, [


                'options' => ['min_range' => 1],


            ]);


            if ($locationId !== false && $locationId !== null) {


                $where[] = 'p.location_id = :location_id';


                $params['location_id'] = (int) $locationId;


            }


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

        $locationFilter = trim((string) ($filters['location'] ?? ''));


        if (preg_match('/^country:([A-Za-z]{2})$/', $locationFilter, $locationCountry) === 1) {


            $where[] = 'UPPER(loc.country_code) = :location_country';


            $params['location_country'] = strtoupper((string) $locationCountry[1]);


        } else {


            $locationId = filter_var($locationFilter !== '' ? $locationFilter : null, FILTER_VALIDATE_INT, [


                'options' => ['min_range' => 1],


            ]);


            if ($locationId !== false && $locationId !== null) {


                $where[] = 'c.location_id = :location_id';


                $params['location_id'] = (int) $locationId;


            }


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
    /* BARAMA_SINGLE_DETAIL_MEDIA_REPOSITORY_V100 */
    public function gallery(string $entityType, int $entityId): array
    {
        if (!in_array($entityType, ['company', 'project', 'property', 'listing'], true)) {
            throw new InvalidArgumentException('Unsupported media entity type.');
        }

        $statement = Database::connection()->prepare(
            'SELECT ma.id AS attachment_id, ma.collection, ma.position, ma.is_primary,
                    m.id AS media_id, m.uuid, m.disk, m.path,
                    m.original_name, m.mime_type,
                    m.width_px, m.height_px, m.alt_text
             FROM media_attachments ma
             INNER JOIN media_assets m ON m.id = ma.media_asset_id
             WHERE ma.entity_type = :entity_type
               AND ma.entity_id = :entity_id
               AND (
                   m.mime_type LIKE "image/%"
                   OR m.mime_type IN ("video/mp4", "video/webm", "video/ogg", "video/quicktime")
                   OR (m.mime_type = "text/x-youtube" AND m.disk = "external")
               )
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
               AND m.disk <> "external"
               AND m.mime_type IN (
                   "image/jpeg", "image/png", "image/webp",
                   "video/mp4", "video/webm", "video/ogg", "video/quicktime"
               )
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
                             ma.entity_type = "site"
                             AND ma.entity_id = 1
                             AND ma.collection IN (
                                 "homepage_hero_image",
                                 "homepage_hero_slider",
                                 "homepage_hero_video"
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

    /* BARAMA_POPULAR_LOCATION_CARDS_V1124 */
    /**
     * Homepage marquee locations. Enabled countries are always preferred.
     * Remaining slots are filled with unique city/province-style locations
     * from countries allowed by the current public location policy.
     *
     * @return array<int,array<string,mixed>>
     */
    public function popularLocationCards(int $limit = 20): array
    {
        $limit = max(2, min(40, $limit));
        $pdo = Database::connection();

        $mode = 'selected';
        $modeStatement = $pdo->prepare(
            'SELECT setting_value FROM application_settings WHERE setting_key = :key LIMIT 1'
        );
        $modeStatement->execute(['key' => 'locations.mode']);
        $storedMode = strtolower(trim((string) $modeStatement->fetchColumn()));
        if (in_array($storedMode, ['selected', 'global'], true)) {
            $mode = $storedMode;
        }

        $countrySql = 'SELECT country_code, name, is_default, sort_order '
            . 'FROM location_countries'
            . ($mode === 'global' ? '' : ' WHERE enabled = 1')
            . ' ORDER BY is_default DESC, sort_order ASC, name ASC';
        $countries = $pdo->query($countrySql)->fetchAll(PDO::FETCH_ASSOC);

        $popularLocations = $this->popularLocations(12);
        $countryScores = [];
        foreach ($popularLocations as $row) {
            $code = strtoupper(trim((string) ($row['country_code'] ?? '')));
            if (preg_match('/^[A-Z]{2}$/', $code) !== 1) {
                continue;
            }
            $countryScores[$code] = ($countryScores[$code] ?? 0) + max(0, (int) ($row['inventory_count'] ?? 0));
        }

        usort($countries, static function (array $a, array $b) use ($countryScores): int {
            $aCode = strtoupper((string) ($a['country_code'] ?? ''));
            $bCode = strtoupper((string) ($b['country_code'] ?? ''));
            $score = ($countryScores[$bCode] ?? 0) <=> ($countryScores[$aCode] ?? 0);
            if ($score !== 0) {
                return $score;
            }
            $default = ((int) ($b['is_default'] ?? 0)) <=> ((int) ($a['is_default'] ?? 0));
            if ($default !== 0) {
                return $default;
            }
            $sort = ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0));
            if ($sort !== 0) {
                return $sort;
            }
            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        $items = [];
        $seenNames = [];
        $countryNames = [];
        $countryOrder = [];

        foreach ($countries as $country) {
            if (count($items) >= $limit) {
                break;
            }
            $code = strtoupper(trim((string) ($country['country_code'] ?? '')));
            $name = trim((string) ($country['name'] ?? ''));
            if (preg_match('/^[A-Z]{2}$/', $code) !== 1 || $name === '') {
                continue;
            }
            $key = mb_strtolower($name, 'UTF-8');
            if (isset($seenNames[$key])) {
                continue;
            }
            $seenNames[$key] = true;
            $countryNames[$code] = $name;
            $countryOrder[] = $code;
            $items[] = [
                'kind' => 'country',
                'name' => $name,
                'country_code' => $code,
                'country_name' => $name,
                'filter_value' => 'country:' . $code,
                'inventory_count' => (int) ($countryScores[$code] ?? 0),
            ];
        }

        if (count($items) >= $limit) {
            return array_slice($items, 0, $limit);
        }

        $resolvedCountry = "COALESCE(NULLIF(UPPER(loc.country_code), ''), ("
            . "SELECT UPPER(lhr.country_code) FROM location_hierarchy_registry lhr "
            . "WHERE lhr.location_id = loc.id ORDER BY lhr.created_location ASC, lhr.external_code ASC LIMIT 1))";
        $policySql = $mode === 'global'
            ? ''
            : ' AND EXISTS (SELECT 1 FROM location_countries allowed '
                . 'WHERE allowed.enabled = 1 AND UPPER(allowed.country_code) = ' . $resolvedCountry . ')';

        $candidateSql = 'SELECT loc.id, loc.name, loc.type, loc.parent_id, loc.sort_order, '
            . $resolvedCountry . ' AS country_code, '
            . '(SELECT c.name FROM location_countries c WHERE UPPER(c.country_code) = '
            . $resolvedCountry . ' LIMIT 1) AS country_name '
            . 'FROM locations loc '
            . "WHERE loc.status = 'active'" . $policySql . ' '
            . "AND LOWER(COALESCE(loc.type, '')) <> 'country' "
            . "ORDER BY CASE WHEN LOWER(COALESCE(loc.type, '')) IN "
            . "('province','state','region','city','municipality','capital') THEN 0 ELSE 1 END ASC, "
            . 'CASE WHEN loc.parent_id IS NULL THEN 0 ELSE 1 END ASC, loc.sort_order ASC, loc.name ASC '
            . 'LIMIT 600';
        $candidateRows = $pdo->query($candidateSql)->fetchAll(PDO::FETCH_ASSOC);

        $byCountry = [];
        foreach ($popularLocations as $row) {
            $code = strtoupper(trim((string) ($row['country_code'] ?? '')));
            $name = trim((string) ($row['name'] ?? ''));
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1 || $name === '' || preg_match('/^[A-Z]{2}$/', $code) !== 1) {
                continue;
            }
            $byCountry[$code][] = [
                'id' => $id,
                'name' => $name,
                'type' => (string) ($row['type'] ?? ''),
                'country_code' => $code,
                'country_name' => (string) ($countryNames[$code] ?? ''),
                'inventory_count' => (int) ($row['inventory_count'] ?? 0),
            ];
        }
        foreach ($candidateRows as $row) {
            $code = strtoupper(trim((string) ($row['country_code'] ?? '')));
            if (preg_match('/^[A-Z]{2}$/', $code) !== 1) {
                continue;
            }
            $byCountry[$code][] = $row;
            if (!isset($countryNames[$code])) {
                $countryNames[$code] = trim((string) ($row['country_name'] ?? ''));
            }
        }

        $appendCandidate = static function (array $row) use (&$items, &$seenNames, &$countryNames, $limit): bool {
            if (count($items) >= $limit) {
                return false;
            }
            $id = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            $code = strtoupper(trim((string) ($row['country_code'] ?? '')));
            if ($id < 1 || $name === '' || preg_match('/^[A-Z]{2}$/', $code) !== 1) {
                return false;
            }
            $key = mb_strtolower($name, 'UTF-8');
            if (isset($seenNames[$key])) {
                return false;
            }
            $seenNames[$key] = true;
            $items[] = [
                'kind' => 'locality',
                'name' => $name,
                'country_code' => $code,
                'country_name' => trim((string) ($row['country_name'] ?? ($countryNames[$code] ?? ''))),
                'filter_value' => (string) $id,
                'inventory_count' => max(0, (int) ($row['inventory_count'] ?? 0)),
            ];
            return true;
        };

        // First distribute fallback localities across countries for flag/name diversity.
        $positions = array_fill_keys($countryOrder, 0);
        $progress = true;
        while (count($items) < $limit && $progress) {
            $progress = false;
            foreach ($countryOrder as $code) {
                $rows = $byCountry[$code] ?? [];
                $position = (int) ($positions[$code] ?? 0);
                while (isset($rows[$position])) {
                    $candidate = $rows[$position];
                    $position++;
                    $positions[$code] = $position;
                    if ($appendCandidate($candidate)) {
                        $progress = true;
                        break;
                    }
                }
                if (count($items) >= $limit) {
                    break;
                }
            }
        }

        // Final deterministic fallback if a country has no distinct preferred locality.
        if (count($items) < $limit) {
            foreach ($candidateRows as $candidate) {
                $appendCandidate($candidate);
                if (count($items) >= $limit) {
                    break;
                }
            }
        }

        return array_slice($items, 0, $limit);
    }

    /* BARAMA_POPULAR_LOCATIONS_V1113 */
    /**
     * Homepage discovery locations ranked by live public inventory.
     * Only active canonical locations allowed by the current country policy are returned.
     *
     * @return array<int,array<string,mixed>>
     */
    public function popularLocations(int $limit = 6): array
    {
        $limit = max(1, min(12, $limit));
        $pdo = Database::connection();

        $mode = 'selected';
        $modeStatement = $pdo->prepare(
            'SELECT setting_value FROM application_settings WHERE setting_key = :key LIMIT 1'
        );
        $modeStatement->execute(['key' => 'locations.mode']);
        $storedMode = strtolower(trim((string) $modeStatement->fetchColumn()));
        if (in_array($storedMode, ['selected', 'global'], true)) {
            $mode = $storedMode;
        }

        $resolvedCountry = "COALESCE(NULLIF(UPPER(loc.country_code), ''), ("
            . "SELECT UPPER(lhr.country_code) FROM location_hierarchy_registry lhr "
            . "WHERE lhr.location_id = loc.id ORDER BY lhr.created_location ASC, lhr.external_code ASC LIMIT 1))";

        $countryPolicy = $mode === 'global'
            ? ''
            : ' AND EXISTS (SELECT 1 FROM location_countries lc '
                . 'WHERE lc.enabled = 1 AND UPPER(lc.country_code) = ' . $resolvedCountry . ')';

        $sql = 'SELECT loc.id, loc.name, loc.type, ' . $resolvedCountry . ' AS country_code, '
            . 'inv.listing_count, inv.project_count, inv.company_count, inv.inventory_count '
            . 'FROM locations loc '
            . 'INNER JOIN ('
            . '  SELECT x.location_id, '
            . '         SUM(x.listing_count) AS listing_count, '
            . '         SUM(x.project_count) AS project_count, '
            . '         SUM(x.company_count) AS company_count, '
            . '         SUM(x.listing_count + x.project_count + x.company_count) AS inventory_count '
            . '  FROM ('
            . '    SELECT p.location_id, COUNT(*) AS listing_count, 0 AS project_count, 0 AS company_count '
            . '    FROM listings l '
            . '    INNER JOIN properties p ON p.id = l.property_id '
            . '    INNER JOIN companies c ON c.id = p.company_id '
            . '    WHERE p.location_id IS NOT NULL '
            . '      AND l.status = "published" AND l.published_at IS NOT NULL AND l.deleted_at IS NULL '
            . '      AND (l.expires_at IS NULL OR l.expires_at > CURRENT_TIMESTAMP) '
            . '      AND p.status = "approved" AND p.deleted_at IS NULL '
            . '      AND c.status = "approved" AND c.published_at IS NOT NULL AND c.deleted_at IS NULL '
            . '    GROUP BY p.location_id '
            . '    UNION ALL '
            . '    SELECT p.location_id, 0, COUNT(*), 0 '
            . '    FROM projects p '
            . '    INNER JOIN companies c ON c.id = p.company_id '
            . '    WHERE p.location_id IS NOT NULL '
            . '      AND p.status = "published" AND p.published_at IS NOT NULL AND p.deleted_at IS NULL '
            . '      AND c.status = "approved" AND c.published_at IS NOT NULL AND c.deleted_at IS NULL '
            . '    GROUP BY p.location_id '
            . '    UNION ALL '
            . '    SELECT c.location_id, 0, 0, COUNT(*) '
            . '    FROM companies c '
            . '    WHERE c.location_id IS NOT NULL '
            . '      AND c.status = "approved" AND c.published_at IS NOT NULL AND c.deleted_at IS NULL '
            . '    GROUP BY c.location_id '
            . '  ) x GROUP BY x.location_id'
            . ') inv ON inv.location_id = loc.id '
            . 'WHERE loc.status = "active"' . $countryPolicy . ' '
            . 'ORDER BY inv.inventory_count DESC, loc.sort_order ASC, loc.name ASC '
            . 'LIMIT :limit';

        $statement = $pdo->prepare($sql);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
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
