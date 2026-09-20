<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use InvalidArgumentException;
use PDO;

final class WorkspaceReadRepository
{
    /** @return array<string, int> */
    public function developerSummary(int $userId): array
    {
        return [
            'companies' => $this->count(
                'SELECT COUNT(*)
                 FROM company_members cm
                 INNER JOIN companies c ON c.id = cm.company_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND c.deleted_at IS NULL',
                ['user_id' => $userId]
            ),
            'projects' => $this->count(
                'SELECT COUNT(*)
                 FROM projects p
                 INNER JOIN company_members cm ON cm.company_id = p.company_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND p.deleted_at IS NULL',
                ['user_id' => $userId]
            ),
            'properties' => $this->count(
                'SELECT COUNT(*)
                 FROM properties p
                 INNER JOIN company_members cm ON cm.company_id = p.company_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND p.deleted_at IS NULL',
                ['user_id' => $userId]
            ),
            'published_listings' => $this->count(
                'SELECT COUNT(*)
                 FROM listings l
                 INNER JOIN properties p ON p.id = l.property_id
                 INNER JOIN company_members cm ON cm.company_id = p.company_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND l.status = "published"
                   AND l.deleted_at IS NULL
                   AND p.deleted_at IS NULL',
                ['user_id' => $userId]
            ),
            'open_inquiries' => $this->count(
                'SELECT COUNT(*)
                 FROM inquiries i
                 INNER JOIN company_members cm ON cm.company_id = i.company_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND i.status IN ("new", "read", "replied")',
                ['user_id' => $userId]
            ),
            'active_leads' => $this->count(
                'SELECT COUNT(*)
                 FROM leads l
                 INNER JOIN company_members cm ON cm.company_id = l.company_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND l.stage NOT IN ("won", "lost")
                   AND l.deleted_at IS NULL',
                ['user_id' => $userId]
            ),
            'active_viewings' => $this->count(
                'SELECT COUNT(*)
                 FROM viewing_requests v
                 INNER JOIN company_members cm ON cm.company_id = v.company_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND v.status IN ("pending", "confirmed", "rescheduled")',
                ['user_id' => $userId]
            ),
        ];
    }

    /**
     * @return array{
     *     companies: array<int, array<string, mixed>>,
     *     projects: array<int, array<string, mixed>>,
     *     properties: array<int, array<string, mixed>>,
     *     locations: array<int, array<string, mixed>>
     * }
     */
    public function developerOptions(int $userId): array
    {
        $pdo = Database::connection();

        $companies = $pdo->prepare(
            'SELECT c.id, c.name, c.status, cm.member_role
             FROM companies c
             INNER JOIN company_members cm ON cm.company_id = c.id
             WHERE cm.user_id = :user_id
               AND cm.status = "active"
               AND c.deleted_at IS NULL
             ORDER BY c.name ASC
             LIMIT 100'
        );
        $companies->execute(['user_id' => $userId]);

        $projects = $pdo->prepare(
            'SELECT p.id, p.name, p.company_id, p.status
             FROM projects p
             INNER JOIN company_members cm ON cm.company_id = p.company_id
             WHERE cm.user_id = :user_id
               AND cm.status = "active"
               AND p.deleted_at IS NULL
             ORDER BY p.name ASC
             LIMIT 200'
        );
        $projects->execute(['user_id' => $userId]);

        $properties = $pdo->prepare(
            'SELECT p.id, p.title, p.reference_code, p.company_id, p.status
             FROM properties p
             INNER JOIN company_members cm ON cm.company_id = p.company_id
             WHERE cm.user_id = :user_id
               AND cm.status = "active"
               AND p.deleted_at IS NULL
             ORDER BY p.title ASC
             LIMIT 300'
        );
        $properties->execute(['user_id' => $userId]);

        $locations = $pdo->query(
            'SELECT id, name, type
             FROM locations
             WHERE status = "active"
             ORDER BY sort_order ASC, name ASC
             LIMIT 300'
        );

        return [
            'companies' => $companies->fetchAll(PDO::FETCH_ASSOC),
            'projects' => $projects->fetchAll(PDO::FETCH_ASSOC),
            'properties' => $properties->fetchAll(PDO::FETCH_ASSOC),
            'locations' => $locations->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    /**
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     pages: int,
     *     per_page: int
     * }
     */
    public function developerSection(
        int $userId,
        string $section,
        string $search,
        int $page,
        int $perPage = 20,
        array $filters = [],
        string $sort = 'updated_desc'
    ): array {
        $search = '%' . trim($search) . '%';

        [$sql, $countSql, $params] = match ($section) {
            'overview', 'inquiries' => [
                'SELECT i.id, i.contact_name, i.contact_email, i.contact_phone,
                        i.status, i.source, i.created_at,
                        c.name AS company_name,
                        p.title AS property_title
                 FROM inquiries i
                 INNER JOIN company_members cm ON cm.company_id = i.company_id
                 LEFT JOIN companies c ON c.id = i.company_id
                 LEFT JOIN properties p ON p.id = i.property_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND CONCAT_WS(" ", i.contact_name, i.contact_email, i.contact_phone) LIKE :search
                 ORDER BY i.created_at DESC',
                'SELECT COUNT(*)
                 FROM inquiries i
                 INNER JOIN company_members cm ON cm.company_id = i.company_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND CONCAT_WS(" ", i.contact_name, i.contact_email, i.contact_phone) LIKE :search',
                ['user_id' => $userId, 'search' => $search],
            ],
            'company' => [
                'SELECT c.id, c.name, c.company_type, c.status, c.email, c.phone,
                        c.location_id, c.updated_at, cm.member_role
                 FROM companies c
                 INNER JOIN company_members cm ON cm.company_id = c.id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND c.deleted_at IS NULL
                   AND c.name LIKE :search
                 ORDER BY c.updated_at DESC',
                'SELECT COUNT(*)
                 FROM companies c
                 INNER JOIN company_members cm ON cm.company_id = c.id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND c.deleted_at IS NULL
                   AND c.name LIKE :search',
                ['user_id' => $userId, 'search' => $search],
            ],
            'projects' => [
                'SELECT p.id, p.name, p.project_type, p.completion_status, p.status,
                        p.location_id, p.updated_at, c.name AS company_name
                 FROM projects p
                 INNER JOIN company_members cm ON cm.company_id = p.company_id
                 INNER JOIN companies c ON c.id = p.company_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND p.deleted_at IS NULL
                   AND p.name LIKE :search
                 ORDER BY p.updated_at DESC',
                'SELECT COUNT(*)
                 FROM projects p
                 INNER JOIN company_members cm ON cm.company_id = p.company_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND p.deleted_at IS NULL
                   AND p.name LIKE :search',
                ['user_id' => $userId, 'search' => $search],
            ],
            'properties' => [
                'SELECT p.id, p.reference_code, p.title, p.property_type, p.status,
                        p.location_id, p.bedrooms, p.bathrooms, p.updated_at,
                        c.name AS company_name
                 FROM properties p
                 INNER JOIN company_members cm ON cm.company_id = p.company_id
                 INNER JOIN companies c ON c.id = p.company_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND p.deleted_at IS NULL
                   AND CONCAT_WS(" ", p.title, p.reference_code) LIKE :search
                 ORDER BY p.updated_at DESC',
                'SELECT COUNT(*)
                 FROM properties p
                 INNER JOIN company_members cm ON cm.company_id = p.company_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND p.deleted_at IS NULL
                   AND CONCAT_WS(" ", p.title, p.reference_code) LIKE :search',
                ['user_id' => $userId, 'search' => $search],
            ],
            'listings' => [
                'SELECT l.id, p.title AS property_title, p.reference_code,
                        p.property_type, p.location_id,
                        l.transaction_type, l.status, l.price, l.currency,
                        l.pricing_period, l.updated_at
                 FROM listings l
                 INNER JOIN properties p ON p.id = l.property_id
                 INNER JOIN company_members cm ON cm.company_id = p.company_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND l.deleted_at IS NULL
                   AND p.deleted_at IS NULL
                   AND CONCAT_WS(" ", p.title, p.reference_code) LIKE :search
                 ORDER BY l.updated_at DESC',
                'SELECT COUNT(*)
                 FROM listings l
                 INNER JOIN properties p ON p.id = l.property_id
                 INNER JOIN company_members cm ON cm.company_id = p.company_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND l.deleted_at IS NULL
                   AND p.deleted_at IS NULL
                   AND CONCAT_WS(" ", p.title, p.reference_code) LIKE :search',
                ['user_id' => $userId, 'search' => $search],
            ],
            'leads' => [
                'SELECT l.id, l.contact_name, l.contact_email, l.contact_phone,
                        l.stage, l.transaction_type, l.preferred_location_id,
                        l.budget_min, l.budget_max,
                        l.currency, l.updated_at, c.name AS company_name
                 FROM leads l
                 INNER JOIN company_members cm ON cm.company_id = l.company_id
                 INNER JOIN companies c ON c.id = l.company_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND l.deleted_at IS NULL
                   AND CONCAT_WS(" ", l.contact_name, l.contact_email, l.contact_phone) LIKE :search
                 ORDER BY l.updated_at DESC',
                'SELECT COUNT(*)
                 FROM leads l
                 INNER JOIN company_members cm ON cm.company_id = l.company_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND l.deleted_at IS NULL
                   AND CONCAT_WS(" ", l.contact_name, l.contact_email, l.contact_phone) LIKE :search',
                ['user_id' => $userId, 'search' => $search],
            ],
            'viewings' => [
                'SELECT v.id, v.status, v.preferred_date, v.preferred_time_window,
                        v.scheduled_for, v.updated_at,
                        u.name AS customer_name, u.email AS customer_email,
                        p.title AS property_title, c.name AS company_name
                 FROM viewing_requests v
                 INNER JOIN company_members cm ON cm.company_id = v.company_id
                 INNER JOIN users u ON u.id = v.user_id
                 INNER JOIN listings l ON l.id = v.listing_id
                 INNER JOIN properties p ON p.id = l.property_id
                 INNER JOIN companies c ON c.id = v.company_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND CONCAT_WS(" ", u.name, u.email, p.title, c.name) LIKE :search
                 ORDER BY v.updated_at DESC',
                'SELECT COUNT(*)
                 FROM viewing_requests v
                 INNER JOIN company_members cm ON cm.company_id = v.company_id
                 INNER JOIN users u ON u.id = v.user_id
                 INNER JOIN listings l ON l.id = v.listing_id
                 INNER JOIN properties p ON p.id = l.property_id
                 INNER JOIN companies c ON c.id = v.company_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND CONCAT_WS(" ", u.name, u.email, p.title, c.name) LIKE :search',
                ['user_id' => $userId, 'search' => $search],
            ],
            'team' => [
                'SELECT *
                 FROM (
                    SELECT "member" AS row_type, u.id, u.name, u.email,
                           cm.member_role, cm.status, cm.joined_at,
                           c.id AS company_id, c.name AS company_name
                    FROM company_members cm
                    INNER JOIN companies c ON c.id = cm.company_id
                    INNER JOIN users u ON u.id = cm.user_id
                    INNER JOIN company_members viewer
                       ON viewer.company_id = cm.company_id
                      AND viewer.user_id = :user_id
                      AND viewer.status = "active"
                    WHERE c.deleted_at IS NULL

                    UNION ALL

                    SELECT "invitation" AS row_type, i.id,
                           "Pending invitation" AS name, i.email,
                           i.member_role, i.status, i.created_at AS joined_at,
                           c.id AS company_id, c.name AS company_name
                    FROM company_invitations i
                    INNER JOIN companies c ON c.id = i.company_id
                    INNER JOIN company_members viewer
                       ON viewer.company_id = i.company_id
                      AND viewer.user_id = :user_id
                      AND viewer.status = "active"
                    WHERE i.status = "pending"
                      AND i.expires_at > CURRENT_TIMESTAMP
                      AND c.deleted_at IS NULL
                 ) team_rows
                 WHERE CONCAT_WS(" ", name, email, company_name) LIKE :search
                 ORDER BY company_name ASC, name ASC',
                'SELECT COUNT(*)
                 FROM (
                    SELECT u.name, u.email, c.name AS company_name
                    FROM company_members cm
                    INNER JOIN companies c ON c.id = cm.company_id
                    INNER JOIN users u ON u.id = cm.user_id
                    INNER JOIN company_members viewer
                       ON viewer.company_id = cm.company_id
                      AND viewer.user_id = :user_id
                      AND viewer.status = "active"
                    WHERE c.deleted_at IS NULL

                    UNION ALL

                    SELECT "Pending invitation" AS name, i.email,
                           c.name AS company_name
                    FROM company_invitations i
                    INNER JOIN companies c ON c.id = i.company_id
                    INNER JOIN company_members viewer
                       ON viewer.company_id = i.company_id
                      AND viewer.user_id = :user_id
                      AND viewer.status = "active"
                    WHERE i.status = "pending"
                      AND i.expires_at > CURRENT_TIMESTAMP
                      AND c.deleted_at IS NULL
                 ) team_rows
                 WHERE CONCAT_WS(" ", name, email, company_name) LIKE :search',
                ['user_id' => $userId, 'search' => $search],
            ],
            'media' => [
                'SELECT m.id, m.original_name, m.mime_type, m.size_bytes,
                        m.width_px, m.height_px, m.created_at
                 FROM media_assets m
                 WHERE m.created_by_user_id = :user_id
                   AND m.original_name LIKE :search
                 ORDER BY m.created_at DESC',
                'SELECT COUNT(*)
                 FROM media_assets m
                 WHERE m.created_by_user_id = :user_id
                   AND m.original_name LIKE :search',
                ['user_id' => $userId, 'search' => $search],
            ],
            default => throw new InvalidArgumentException('Unsupported developer workspace section.'),
        };

        [$sql, $countSql, $params] = $this->applyTableOptions(
            false,
            $section,
            $sql,
            $params,
            $filters,
            $sort
        );

        return $this->paginate($sql, $countSql, $params, $page, $perPage);
    }

    /** @return array<string, int> */
    public function adminSummary(): array
    {
        return [
            'pending_approvals' => $this->count(
                'SELECT
                    (SELECT COUNT(*) FROM companies WHERE status = "pending" AND deleted_at IS NULL)
                  + (SELECT COUNT(*) FROM projects WHERE status = "pending" AND deleted_at IS NULL)
                  + (SELECT COUNT(*) FROM properties WHERE status = "pending" AND deleted_at IS NULL)
                  + (SELECT COUNT(*) FROM listings WHERE status = "pending" AND deleted_at IS NULL)'
            ),
            'companies' => $this->count(
                'SELECT COUNT(*) FROM companies WHERE deleted_at IS NULL'
            ),
            'properties' => $this->count(
                'SELECT COUNT(*) FROM properties WHERE deleted_at IS NULL'
            ),
            'published_listings' => $this->count(
                'SELECT COUNT(*) FROM listings
                 WHERE status = "published" AND deleted_at IS NULL'
            ),
            'open_inquiries' => $this->count(
                'SELECT COUNT(*) FROM inquiries
                 WHERE status IN ("new", "read", "replied")'
            ),
            'active_leads' => $this->count(
                'SELECT COUNT(*) FROM leads
                 WHERE stage NOT IN ("won", "lost") AND deleted_at IS NULL'
            ),
        ];
    }

    /**
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     pages: int,
     *     per_page: int
     * }
     */
    public function adminSection(
        string $section,
        string $search,
        int $page,
        int $perPage = 20,
        array $filters = [],
        string $sort = 'updated_desc'
    ): array {
        $search = '%' . trim($search) . '%';

        [$sql, $countSql, $params] = match ($section) {
            'overview', 'approvals' => [
                'SELECT *
                 FROM (
                    SELECT "company" AS entity_type, id, name AS title,
                           status, updated_at, company_type AS context
                    FROM companies
                    WHERE status = "pending" AND deleted_at IS NULL
                    UNION ALL
                    SELECT "project" AS entity_type, id, name AS title,
                           status, updated_at, project_type AS context
                    FROM projects
                    WHERE status = "pending" AND deleted_at IS NULL
                    UNION ALL
                    SELECT "property" AS entity_type, id, title,
                           status, updated_at, property_type AS context
                    FROM properties
                    WHERE status = "pending" AND deleted_at IS NULL
                    UNION ALL
                    SELECT "listing" AS entity_type, l.id, p.title,
                           l.status, l.updated_at, l.transaction_type AS context
                    FROM listings l
                    INNER JOIN properties p ON p.id = l.property_id
                    WHERE l.status = "pending"
                      AND l.deleted_at IS NULL
                      AND p.deleted_at IS NULL
                 ) queue
                 WHERE queue.title LIKE :search
                 ORDER BY queue.updated_at DESC',
                'SELECT COUNT(*)
                 FROM (
                    SELECT name AS title
                    FROM companies
                    WHERE status = "pending" AND deleted_at IS NULL
                    UNION ALL
                    SELECT name AS title
                    FROM projects
                    WHERE status = "pending" AND deleted_at IS NULL
                    UNION ALL
                    SELECT title
                    FROM properties
                    WHERE status = "pending" AND deleted_at IS NULL
                    UNION ALL
                    SELECT p.title
                    FROM listings l
                    INNER JOIN properties p ON p.id = l.property_id
                    WHERE l.status = "pending"
                      AND l.deleted_at IS NULL
                      AND p.deleted_at IS NULL
                 ) queue
                 WHERE queue.title LIKE :search',
                ['search' => $search],
            ],
            'companies' => [
                'SELECT id, name, company_type, status, email, phone,
                        location_id, verified_at, updated_at
                 FROM companies
                 WHERE deleted_at IS NULL
                   AND name LIKE :search
                 ORDER BY updated_at DESC',
                'SELECT COUNT(*)
                 FROM companies
                 WHERE deleted_at IS NULL
                   AND name LIKE :search',
                ['search' => $search],
            ],
            'projects' => [
                'SELECT p.id, p.name, p.project_type, p.completion_status,
                        p.location_id, p.status, p.updated_at, c.name AS company_name
                 FROM projects p
                 INNER JOIN companies c ON c.id = p.company_id
                 WHERE p.deleted_at IS NULL
                   AND p.name LIKE :search
                 ORDER BY p.updated_at DESC',
                'SELECT COUNT(*)
                 FROM projects
                 WHERE deleted_at IS NULL
                   AND name LIKE :search',
                ['search' => $search],
            ],
            'properties' => [
                'SELECT p.id, p.reference_code, p.title, p.property_type,
                        p.location_id, p.status, p.updated_at, c.name AS company_name
                 FROM properties p
                 LEFT JOIN companies c ON c.id = p.company_id
                 WHERE p.deleted_at IS NULL
                   AND CONCAT_WS(" ", p.title, p.reference_code) LIKE :search
                 ORDER BY p.updated_at DESC',
                'SELECT COUNT(*)
                 FROM properties
                 WHERE deleted_at IS NULL
                   AND CONCAT_WS(" ", title, reference_code) LIKE :search',
                ['search' => $search],
            ],
            'listings' => [
                'SELECT l.id, p.title AS property_title, p.reference_code,
                        p.property_type, p.location_id,
                        l.transaction_type, l.status, l.price, l.currency,
                        l.updated_at
                 FROM listings l
                 INNER JOIN properties p ON p.id = l.property_id
                 WHERE l.deleted_at IS NULL
                   AND p.deleted_at IS NULL
                   AND CONCAT_WS(" ", p.title, p.reference_code) LIKE :search
                 ORDER BY l.updated_at DESC',
                'SELECT COUNT(*)
                 FROM listings l
                 INNER JOIN properties p ON p.id = l.property_id
                 WHERE l.deleted_at IS NULL
                   AND p.deleted_at IS NULL
                   AND CONCAT_WS(" ", p.title, p.reference_code) LIKE :search',
                ['search' => $search],
            ],
            'inquiries' => [
                'SELECT i.id, i.contact_name, i.contact_email, i.contact_phone,
                        i.status, i.source, i.created_at, c.name AS company_name
                 FROM inquiries i
                 LEFT JOIN companies c ON c.id = i.company_id
                 WHERE CONCAT_WS(" ", i.contact_name, i.contact_email, i.contact_phone) LIKE :search
                 ORDER BY i.created_at DESC',
                'SELECT COUNT(*)
                 FROM inquiries
                 WHERE CONCAT_WS(" ", contact_name, contact_email, contact_phone) LIKE :search',
                ['search' => $search],
            ],
            'leads' => [
                'SELECT l.id, l.contact_name, l.contact_email, l.contact_phone,
                        l.stage, l.transaction_type, l.preferred_location_id,
                        l.budget_min, l.budget_max,
                        l.currency, l.updated_at, c.name AS company_name
                 FROM leads l
                 INNER JOIN companies c ON c.id = l.company_id
                 WHERE l.deleted_at IS NULL
                   AND CONCAT_WS(" ", l.contact_name, l.contact_email, l.contact_phone) LIKE :search
                 ORDER BY l.updated_at DESC',
                'SELECT COUNT(*)
                 FROM leads
                 WHERE deleted_at IS NULL
                   AND CONCAT_WS(" ", contact_name, contact_email, contact_phone) LIKE :search',
                ['search' => $search],
            ],
            'catalog' => [
                'SELECT *
                 FROM (
                    SELECT "location" AS entity_type, id, name AS title,
                           status, type AS context, updated_at
                    FROM locations
                    UNION ALL
                    SELECT "amenity" AS entity_type, id, name AS title,
                           status, category AS context, updated_at
                    FROM amenities
                 ) catalog
                 WHERE catalog.title LIKE :search
                 ORDER BY catalog.entity_type ASC, catalog.title ASC',
                'SELECT COUNT(*)
                 FROM (
                    SELECT name AS title FROM locations
                    UNION ALL
                    SELECT name AS title FROM amenities
                 ) catalog
                 WHERE catalog.title LIKE :search',
                ['search' => $search],
            ],
            'media' => [
                'SELECT m.id, m.original_name, m.mime_type, m.size_bytes,
                        m.width_px, m.height_px, m.created_at,
                        u.name AS uploaded_by
                 FROM media_assets m
                 LEFT JOIN users u ON u.id = m.created_by_user_id
                 WHERE m.original_name LIKE :search
                 ORDER BY m.created_at DESC',
                'SELECT COUNT(*)
                 FROM media_assets
                 WHERE original_name LIKE :search',
                ['search' => $search],
            ],
            default => throw new InvalidArgumentException('Unsupported admin workspace section.'),
        };

        [$sql, $countSql, $params] = $this->applyTableOptions(
            true,
            $section,
            $sql,
            $params,
            $filters,
            $sort
        );

        return $this->paginate($sql, $countSql, $params, $page, $perPage);
    }

    /** @return array<int, array<string, mixed>> */
    public function adminLocations(): array
    {
        return Database::connection()
            ->query(
                'SELECT id, name, type
                 FROM locations
                 WHERE status = "active"
                 ORDER BY sort_order ASC, name ASC
                 LIMIT 300'
            )
            ->fetchAll(PDO::FETCH_ASSOC);
    }


    /**
     * @param array<string, int|string> $params
     * @param array<string, mixed> $filters
     * @return array{0:string,1:string,2:array<string,int|string>}
     */
    private function applyTableOptions(
        bool $admin,
        string $section,
        string $sql,
        array $params,
        array $filters,
        string $sort
    ): array {
        $key = $admin ? 'admin:' . $section : 'developer:' . $section;

        $definitions = [
            'developer:overview' => [
                'filters' => ['status' => 'status'],
                'sorts' => ['updated_desc' => 'created_at DESC', 'updated_asc' => 'created_at ASC'],
            ],
            'developer:inquiries' => [
                'filters' => ['status' => 'status'],
                'sorts' => ['updated_desc' => 'created_at DESC', 'updated_asc' => 'created_at ASC'],
            ],
            'developer:company' => [
                'filters' => ['status' => 'status', 'type' => 'company_type', 'location' => 'location_id'],
                'sorts' => [
                    'updated_desc' => 'updated_at DESC',
                    'updated_asc' => 'updated_at ASC',
                    'name_asc' => 'name ASC',
                    'name_desc' => 'name DESC',
                ],
            ],
            'developer:projects' => [
                'filters' => ['status' => 'status', 'type' => 'project_type', 'location' => 'location_id'],
                'sorts' => [
                    'updated_desc' => 'updated_at DESC',
                    'updated_asc' => 'updated_at ASC',
                    'name_asc' => 'name ASC',
                    'name_desc' => 'name DESC',
                ],
            ],
            'developer:properties' => [
                'filters' => ['status' => 'status', 'type' => 'property_type', 'location' => 'location_id'],
                'sorts' => [
                    'updated_desc' => 'updated_at DESC',
                    'updated_asc' => 'updated_at ASC',
                    'name_asc' => 'title ASC',
                    'name_desc' => 'title DESC',
                ],
            ],
            'developer:listings' => [
                'filters' => ['status' => 'status', 'type' => 'transaction_type', 'location' => 'location_id'],
                'sorts' => [
                    'updated_desc' => 'updated_at DESC',
                    'updated_asc' => 'updated_at ASC',
                    'price_asc' => 'price ASC',
                    'price_desc' => 'price DESC',
                    'name_asc' => 'property_title ASC',
                ],
            ],
            'developer:leads' => [
                'filters' => ['status' => 'stage', 'type' => 'transaction_type', 'location' => 'preferred_location_id'],
                'sorts' => [
                    'updated_desc' => 'updated_at DESC',
                    'updated_asc' => 'updated_at ASC',
                    'price_asc' => 'budget_max ASC',
                    'price_desc' => 'budget_max DESC',
                    'name_asc' => 'contact_name ASC',
                ],
            ],
            'developer:viewings' => [
                'filters' => ['status' => 'status'],
                'sorts' => [
                    'updated_desc' => 'updated_at DESC',
                    'updated_asc' => 'updated_at ASC',
                    'name_asc' => 'customer_name ASC',
                ],
            ],
            'developer:team' => [
                'filters' => ['status' => 'status', 'type' => 'member_role'],
                'sorts' => ['name_asc' => 'name ASC', 'name_desc' => 'name DESC', 'updated_desc' => 'joined_at DESC'],
            ],
            'developer:media' => [
                'filters' => ['type' => 'mime_type'],
                'sorts' => ['updated_desc' => 'created_at DESC', 'updated_asc' => 'created_at ASC', 'name_asc' => 'original_name ASC'],
            ],
            'admin:overview' => [
                'filters' => ['type' => 'entity_type'],
                'sorts' => ['updated_desc' => 'updated_at DESC', 'updated_asc' => 'updated_at ASC', 'name_asc' => 'title ASC'],
            ],
            'admin:approvals' => [
                'filters' => ['type' => 'entity_type'],
                'sorts' => ['updated_desc' => 'updated_at DESC', 'updated_asc' => 'updated_at ASC', 'name_asc' => 'title ASC'],
            ],
            'admin:companies' => [
                'filters' => ['status' => 'status', 'type' => 'company_type', 'location' => 'location_id'],
                'sorts' => ['updated_desc' => 'updated_at DESC', 'updated_asc' => 'updated_at ASC', 'name_asc' => 'name ASC', 'name_desc' => 'name DESC'],
            ],
            'admin:projects' => [
                'filters' => ['status' => 'status', 'type' => 'project_type', 'location' => 'location_id'],
                'sorts' => ['updated_desc' => 'updated_at DESC', 'updated_asc' => 'updated_at ASC', 'name_asc' => 'name ASC', 'name_desc' => 'name DESC'],
            ],
            'admin:properties' => [
                'filters' => ['status' => 'status', 'type' => 'property_type', 'location' => 'location_id'],
                'sorts' => ['updated_desc' => 'updated_at DESC', 'updated_asc' => 'updated_at ASC', 'name_asc' => 'title ASC', 'name_desc' => 'title DESC'],
            ],
            'admin:listings' => [
                'filters' => ['status' => 'status', 'type' => 'transaction_type', 'location' => 'location_id'],
                'sorts' => ['updated_desc' => 'updated_at DESC', 'updated_asc' => 'updated_at ASC', 'price_asc' => 'price ASC', 'price_desc' => 'price DESC', 'name_asc' => 'property_title ASC'],
            ],
            'admin:inquiries' => [
                'filters' => ['status' => 'status'],
                'sorts' => ['updated_desc' => 'created_at DESC', 'updated_asc' => 'created_at ASC', 'name_asc' => 'contact_name ASC'],
            ],
            'admin:leads' => [
                'filters' => ['status' => 'stage', 'type' => 'transaction_type', 'location' => 'preferred_location_id'],
                'sorts' => ['updated_desc' => 'updated_at DESC', 'updated_asc' => 'updated_at ASC', 'price_asc' => 'budget_max ASC', 'price_desc' => 'budget_max DESC', 'name_asc' => 'contact_name ASC'],
            ],
            'admin:catalog' => [
                'filters' => ['status' => 'status', 'type' => 'entity_type'],
                'sorts' => ['name_asc' => 'title ASC', 'name_desc' => 'title DESC', 'updated_desc' => 'updated_at DESC'],
            ],
            'admin:media' => [
                'filters' => ['type' => 'mime_type'],
                'sorts' => ['updated_desc' => 'created_at DESC', 'updated_asc' => 'created_at ASC', 'name_asc' => 'original_name ASC'],
            ],
        ];

        $definition = $definitions[$key] ?? ['filters' => [], 'sorts' => []];
        $baseSql = preg_replace('/\s+ORDER\s+BY\s+.+$/is', '', trim($sql));

        if (!is_string($baseSql) || $baseSql === '') {
            throw new InvalidArgumentException('Workspace table query could not be prepared.');
        }

        $outerWhere = [];
        $filterParams = [];

        foreach ($definition['filters'] as $filterKey => $column) {
            $value = trim((string) ($filters[$filterKey] ?? ''));

            if ($value === '') {
                continue;
            }

            if ($filterKey === 'location') {
                if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1) {
                    continue;
                }
                $filterParams['filter_' . $filterKey] = (int) $value;
            } else {
                $filterParams['filter_' . $filterKey] = mb_substr($value, 0, 80);
            }

            $outerWhere[] = $column . ' = :filter_' . $filterKey;
        }

        $whereSql = $outerWhere === []
            ? ''
            : ' WHERE ' . implode(' AND ', $outerWhere);

        $sorts = $definition['sorts'];
        $orderSql = $sorts[$sort] ?? $sorts['updated_desc'] ?? $sorts['name_asc'] ?? 'id DESC';

        $wrapped = 'SELECT * FROM (' . $baseSql . ') workspace_rows'
            . $whereSql
            . ' ORDER BY ' . $orderSql;

        $count = 'SELECT COUNT(*) FROM (' . $baseSql . ') workspace_rows'
            . $whereSql;

        return [
            $wrapped,
            $count,
            array_merge($params, $filterParams),
        ];
    }

    /**
     * @param array<string, int|string> $params
     * @return array{
     *     rows: array<int, array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     pages: int,
     *     per_page: int
     * }
     */
    private function paginate(
        string $sql,
        string $countSql,
        array $params,
        int $page,
        int $perPage
    ): array {
        $perPage = max(5, min(50, $perPage));
        $page = max(1, $page);
        $total = $this->count($countSql, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $statement = Database::connection()->prepare(
            $sql . ' LIMIT :limit OFFSET :offset'
        );

        foreach ($params as $key => $value) {
            $statement->bindValue(
                ':' . $key,
                $value,
                is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }

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

    /** @param array<string, int|string> $params */
    private function count(string $sql, array $params = []): int
    {
        $statement = Database::connection()->prepare($sql);

        foreach ($params as $key => $value) {
            $statement->bindValue(
                ':' . $key,
                $value,
                is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }

        $statement->execute();

        return (int) $statement->fetchColumn();
    }
}
