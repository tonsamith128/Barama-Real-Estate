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
     *     listings: array<int, array<string, mixed>>,
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

        $listings = $pdo->prepare(
            'SELECT l.id, l.property_id, l.transaction_type, l.status,
                    p.title AS property_title, p.reference_code, p.company_id,
                    c.name AS company_name
             FROM listings l
             INNER JOIN properties p ON p.id = l.property_id
             INNER JOIN companies c ON c.id = p.company_id
             INNER JOIN company_members cm ON cm.company_id = p.company_id
             WHERE cm.user_id = :user_id
               AND cm.status = "active"
               AND l.deleted_at IS NULL
               AND p.deleted_at IS NULL
             ORDER BY p.title ASC, l.id ASC
             LIMIT 400'
        );
        $listings->execute(['user_id' => $userId]);

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
            'listings' => $listings->fetchAll(PDO::FETCH_ASSOC),
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
                        i.status, i.source, i.created_at, i.responded_at,
                        i.assigned_user_id,
                        c.name AS company_name,
                        p.title AS property_title, p.reference_code,
                        p.property_type, p.location_id,
                        loc.name AS location_name,
                        l.transaction_type, l.price, l.currency,
                        assigned.name AS assigned_user_name,
                        (SELECT MIN(ld.id) FROM leads ld WHERE ld.inquiry_id = i.id AND ld.deleted_at IS NULL) AS converted_lead_id,
                        (SELECT ld.stage FROM leads ld WHERE ld.inquiry_id = i.id AND ld.deleted_at IS NULL ORDER BY ld.id ASC LIMIT 1) AS converted_lead_stage
                 FROM inquiries i
                 INNER JOIN company_members cm ON cm.company_id = i.company_id
                 LEFT JOIN companies c ON c.id = i.company_id
                 LEFT JOIN properties p ON p.id = i.property_id
                 LEFT JOIN locations loc ON loc.id = p.location_id
                 LEFT JOIN listings l ON l.id = i.listing_id
                 LEFT JOIN users assigned ON assigned.id = i.assigned_user_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND CONCAT_WS(" ", i.contact_name, i.contact_email, i.contact_phone, c.name, p.title, p.reference_code) LIKE :search
                 ORDER BY i.created_at DESC',
                'SELECT COUNT(*)
                 FROM inquiries i
                 INNER JOIN company_members cm ON cm.company_id = i.company_id
                 LEFT JOIN companies c ON c.id = i.company_id
                 LEFT JOIN properties p ON p.id = i.property_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND CONCAT_WS(" ", i.contact_name, i.contact_email, i.contact_phone, c.name, p.title, p.reference_code) LIKE :search',
                ['user_id' => $userId, 'search' => $search],
            ],
            'company' => [
                'SELECT c.id, c.name, c.company_type, c.status, c.email, c.phone,
                        c.location_id, c.updated_at, cm.member_role,
                        loc.name AS location_name,
                        (SELECT COUNT(*) FROM projects p2 WHERE p2.company_id = c.id AND p2.deleted_at IS NULL) AS projects_count,
                        (SELECT COUNT(*) FROM properties p3 WHERE p3.company_id = c.id AND p3.deleted_at IS NULL) AS properties_count
                 FROM companies c
                 INNER JOIN company_members cm ON cm.company_id = c.id
                 LEFT JOIN locations loc ON loc.id = c.location_id
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
                        p.location_id, p.updated_at, c.name AS company_name,
                        loc.name AS location_name,
                        (SELECT COUNT(*) FROM properties prop WHERE prop.project_id = p.id AND prop.deleted_at IS NULL) AS properties_count
                 FROM projects p
                 INNER JOIN company_members cm ON cm.company_id = p.company_id
                 INNER JOIN companies c ON c.id = p.company_id
                 LEFT JOIN locations loc ON loc.id = p.location_id
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
                        c.name AS company_name, loc.name AS location_name,
                        (SELECT COUNT(*) FROM listings li WHERE li.property_id = p.id AND li.deleted_at IS NULL) AS listings_count
                 FROM properties p
                 INNER JOIN company_members cm ON cm.company_id = p.company_id
                 INNER JOIN companies c ON c.id = p.company_id
                 LEFT JOIN locations loc ON loc.id = p.location_id
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
                        p.property_type, p.location_id, c.name AS company_name,
                        loc.name AS location_name,
                        l.transaction_type, l.status, l.price, l.currency,
                        l.pricing_period, l.expires_at, l.updated_at
                 FROM listings l
                 INNER JOIN properties p ON p.id = l.property_id
                 INNER JOIN company_members cm ON cm.company_id = p.company_id
                 INNER JOIN companies c ON c.id = p.company_id
                 LEFT JOIN locations loc ON loc.id = p.location_id
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
                'SELECT l.id, l.inquiry_id, l.contact_name, l.contact_email, l.contact_phone,
                        l.stage, l.transaction_type, l.property_type,
                        l.preferred_location_id, l.budget_min, l.budget_max,
                        l.currency, l.assigned_user_id, l.updated_at,
                        c.name AS company_name,
                        assigned.name AS assigned_user_name,
                        loc.name AS preferred_location_name,
                        i.id AS source_inquiry_id,
                        p.title AS source_property_title,
                        p.reference_code AS source_property_reference,
                        listing.slug AS source_listing_slug
                 FROM leads l
                 INNER JOIN company_members cm ON cm.company_id = l.company_id
                 INNER JOIN companies c ON c.id = l.company_id
                 LEFT JOIN users assigned ON assigned.id = l.assigned_user_id
                 LEFT JOIN locations loc ON loc.id = l.preferred_location_id
                 LEFT JOIN inquiries i ON i.id = l.inquiry_id
                 LEFT JOIN properties p ON p.id = i.property_id
                 LEFT JOIN listings listing ON listing.id = i.listing_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND l.deleted_at IS NULL
                   AND CONCAT_WS(" ", l.contact_name, l.contact_email, l.contact_phone, c.name, assigned.name, loc.name, p.title, p.reference_code) LIKE :search
                 ORDER BY l.updated_at DESC',
                'SELECT COUNT(*)
                 FROM leads l
                 INNER JOIN company_members cm ON cm.company_id = l.company_id
                 INNER JOIN companies c ON c.id = l.company_id
                 LEFT JOIN users assigned ON assigned.id = l.assigned_user_id
                 LEFT JOIN locations loc ON loc.id = l.preferred_location_id
                 LEFT JOIN inquiries i ON i.id = l.inquiry_id
                 LEFT JOIN properties p ON p.id = i.property_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND l.deleted_at IS NULL
                   AND CONCAT_WS(" ", l.contact_name, l.contact_email, l.contact_phone, c.name, assigned.name, loc.name, p.title, p.reference_code) LIKE :search',
                ['user_id' => $userId, 'search' => $search],
            ],
            'viewings' => [
                'SELECT v.id, v.status, v.preferred_date, v.preferred_time_window,
                        v.alternate_date, v.alternate_time_window,
                        v.scheduled_for, v.company_note, v.created_at, v.updated_at,
                        u.name AS customer_name, u.email AS customer_email,
                        p.title AS property_title, p.reference_code,
                        p.property_type, p.location_id,
                        loc.name AS location_name,
                        c.name AS company_name,
                        l.transaction_type, l.price, l.currency
                 FROM viewing_requests v
                 INNER JOIN company_members cm ON cm.company_id = v.company_id
                 INNER JOIN users u ON u.id = v.user_id
                 INNER JOIN listings l ON l.id = v.listing_id
                 INNER JOIN properties p ON p.id = l.property_id
                 INNER JOIN companies c ON c.id = v.company_id
                 LEFT JOIN locations loc ON loc.id = p.location_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND CONCAT_WS(" ", u.name, u.email, p.title, p.reference_code, c.name, loc.name) LIKE :search
                 ORDER BY v.updated_at DESC',
                'SELECT COUNT(*)
                 FROM viewing_requests v
                 INNER JOIN company_members cm ON cm.company_id = v.company_id
                 INNER JOIN users u ON u.id = v.user_id
                 INNER JOIN listings l ON l.id = v.listing_id
                 INNER JOIN properties p ON p.id = l.property_id
                 INNER JOIN companies c ON c.id = v.company_id
                 LEFT JOIN locations loc ON loc.id = p.location_id
                 WHERE cm.user_id = :user_id
                   AND cm.status = "active"
                   AND CONCAT_WS(" ", u.name, u.email, p.title, p.reference_code, c.name, loc.name) LIKE :search',
                ['user_id' => $userId, 'search' => $search],
            ],
            'team' => [
                'SELECT *
                 FROM (
                    SELECT "member" AS row_type, u.id, u.name, u.email,
                           cm.member_role, cm.status, cm.joined_at,
                           NULL AS expires_at,
                           c.id AS company_id, c.name AS company_name,
                           viewer.member_role AS viewer_role
                    FROM company_members cm
                    INNER JOIN companies c ON c.id = cm.company_id
                    INNER JOIN users u ON u.id = cm.user_id
                    INNER JOIN company_members viewer
                       ON viewer.company_id = cm.company_id
                      AND viewer.user_id = :team_user_member
                      AND viewer.status = "active"
                    WHERE c.deleted_at IS NULL

                    UNION ALL

                    SELECT "invitation" AS row_type, i.id,
                           "Pending invitation" AS name, i.email,
                           i.member_role, i.status, i.created_at AS joined_at,
                           i.expires_at,
                           c.id AS company_id, c.name AS company_name,
                           viewer.member_role AS viewer_role
                    FROM company_invitations i
                    INNER JOIN companies c ON c.id = i.company_id
                    INNER JOIN company_members viewer
                       ON viewer.company_id = i.company_id
                      AND viewer.user_id = :team_user_invite
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
                      AND viewer.user_id = :team_user_member
                      AND viewer.status = "active"
                    WHERE c.deleted_at IS NULL

                    UNION ALL

                    SELECT "Pending invitation" AS name, i.email,
                           c.name AS company_name
                    FROM company_invitations i
                    INNER JOIN companies c ON c.id = i.company_id
                    INNER JOIN company_members viewer
                       ON viewer.company_id = i.company_id
                      AND viewer.user_id = :team_user_invite
                      AND viewer.status = "active"
                    WHERE i.status = "pending"
                      AND i.expires_at > CURRENT_TIMESTAMP
                      AND c.deleted_at IS NULL
                 ) team_rows
                 WHERE CONCAT_WS(" ", name, email, company_name) LIKE :search',
                [
                    'team_user_member' => $userId,
                    'team_user_invite' => $userId,
                    'search' => $search,
                ],
            ],
            'media' => [
                'SELECT ma.id, m.id AS media_id, m.uuid, m.original_name,
                        m.mime_type, m.size_bytes, m.width_px, m.height_px,
                        m.alt_text, m.created_at,
                        ma.entity_type, ma.entity_id, ma.collection,
                        ma.position, ma.is_primary,
                        CASE ma.entity_type
                            WHEN "company" THEN company_target.name
                            WHEN "project" THEN project_target.name
                            WHEN "property" THEN property_target.title
                            WHEN "listing" THEN listing_property.title
                            ELSE NULL
                        END AS target_title,
                        COALESCE(
                            company_target.name,
                            project_company.name,
                            property_company.name,
                            listing_company.name
                        ) AS company_name,
                        CASE ma.entity_type
                            WHEN "company" THEN company_target.status
                            WHEN "project" THEN project_target.status
                            WHEN "property" THEN property_target.status
                            WHEN "listing" THEN listing_target.status
                            ELSE NULL
                        END AS target_status
                 FROM media_attachments ma
                 INNER JOIN media_assets m ON m.id = ma.media_asset_id
                 LEFT JOIN companies company_target
                    ON ma.entity_type = "company" AND company_target.id = ma.entity_id
                 LEFT JOIN projects project_target
                    ON ma.entity_type = "project" AND project_target.id = ma.entity_id
                 LEFT JOIN companies project_company
                    ON project_company.id = project_target.company_id
                 LEFT JOIN properties property_target
                    ON ma.entity_type = "property" AND property_target.id = ma.entity_id
                 LEFT JOIN companies property_company
                    ON property_company.id = property_target.company_id
                 LEFT JOIN listings listing_target
                    ON ma.entity_type = "listing" AND listing_target.id = ma.entity_id
                 LEFT JOIN properties listing_property
                    ON listing_property.id = listing_target.property_id
                 LEFT JOIN companies listing_company
                    ON listing_company.id = listing_property.company_id
                 WHERE EXISTS (
                    SELECT 1
                    FROM company_members viewer
                    WHERE viewer.user_id = :user_id
                      AND viewer.status = "active"
                      AND viewer.company_id = COALESCE(
                        company_target.id,
                        project_target.company_id,
                        property_target.company_id,
                        listing_property.company_id
                      )
                 )
                   AND CONCAT_WS(
                        " ",
                        m.original_name,
                        m.alt_text,
                        company_target.name,
                        project_target.name,
                        property_target.title,
                        listing_property.title,
                        project_company.name,
                        property_company.name,
                        listing_company.name
                   ) LIKE :search
                 ORDER BY m.created_at DESC, ma.id DESC',
                'SELECT COUNT(*)
                 FROM media_attachments ma
                 INNER JOIN media_assets m ON m.id = ma.media_asset_id
                 LEFT JOIN companies company_target
                    ON ma.entity_type = "company" AND company_target.id = ma.entity_id
                 LEFT JOIN projects project_target
                    ON ma.entity_type = "project" AND project_target.id = ma.entity_id
                 LEFT JOIN properties property_target
                    ON ma.entity_type = "property" AND property_target.id = ma.entity_id
                 LEFT JOIN listings listing_target
                    ON ma.entity_type = "listing" AND listing_target.id = ma.entity_id
                 LEFT JOIN properties listing_property
                    ON listing_property.id = listing_target.property_id
                 WHERE EXISTS (
                    SELECT 1
                    FROM company_members viewer
                    WHERE viewer.user_id = :user_id
                      AND viewer.status = "active"
                      AND viewer.company_id = COALESCE(
                        company_target.id,
                        project_target.company_id,
                        property_target.company_id,
                        listing_property.company_id
                      )
                 )
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

    /** @return array<string,int> */
    public function developerWorkspaceManagementSummary(int $userId, string $section): array
    {
        $pdo = Database::connection();

        if ($section === 'team') {
            $statement = $pdo->prepare(
                'SELECT
                    (
                        SELECT COUNT(*)
                        FROM company_members cm
                        WHERE cm.status = "active"
                          AND EXISTS (
                            SELECT 1 FROM company_members viewer
                            WHERE viewer.company_id = cm.company_id
                              AND viewer.user_id = :user_id
                              AND viewer.status = "active"
                          )
                    )
                    +
                    (
                        SELECT COUNT(*)
                        FROM company_invitations i
                        WHERE i.status = "pending"
                          AND i.expires_at > CURRENT_TIMESTAMP
                          AND EXISTS (
                            SELECT 1 FROM company_members viewer
                            WHERE viewer.company_id = i.company_id
                              AND viewer.user_id = :user_id2
                              AND viewer.status = "active"
                          )
                    ) AS total,
                    (
                        SELECT COUNT(*)
                        FROM company_members cm
                        WHERE cm.status = "active"
                          AND EXISTS (
                            SELECT 1 FROM company_members viewer
                            WHERE viewer.company_id = cm.company_id
                              AND viewer.user_id = :user_id3
                              AND viewer.status = "active"
                          )
                    ) AS active_count,
                    (
                        SELECT COUNT(*)
                        FROM company_members cm
                        WHERE cm.status = "active"
                          AND cm.member_role = "manager"
                          AND EXISTS (
                            SELECT 1 FROM company_members viewer
                            WHERE viewer.company_id = cm.company_id
                              AND viewer.user_id = :user_id4
                              AND viewer.status = "active"
                          )
                    ) AS managers_count,
                    (
                        SELECT COUNT(*)
                        FROM company_members cm
                        WHERE cm.status = "active"
                          AND cm.member_role = "agent"
                          AND EXISTS (
                            SELECT 1 FROM company_members viewer
                            WHERE viewer.company_id = cm.company_id
                              AND viewer.user_id = :user_id5
                              AND viewer.status = "active"
                          )
                    ) AS agents_count,
                    (
                        SELECT COUNT(*)
                        FROM company_invitations i
                        WHERE i.status = "pending"
                          AND i.expires_at > CURRENT_TIMESTAMP
                          AND EXISTS (
                            SELECT 1 FROM company_members viewer
                            WHERE viewer.company_id = i.company_id
                              AND viewer.user_id = :user_id6
                              AND viewer.status = "active"
                          )
                    ) AS pending_count'
            );
            $statement->execute([
                'user_id' => $userId,
                'user_id2' => $userId,
                'user_id3' => $userId,
                'user_id4' => $userId,
                'user_id5' => $userId,
                'user_id6' => $userId,
            ]);
        } elseif ($section === 'media') {
            $statement = $pdo->prepare(
                'SELECT
                    COUNT(*) AS total,
                    SUM(m.mime_type LIKE "image/%") AS images_count,
                    SUM(m.mime_type = "application/pdf") AS pdfs_count,
                    SUM(ma.is_primary = 1) AS primary_count,
                    SUM(
                        CASE ma.entity_type
                            WHEN "company" THEN company_target.status NOT IN ("draft", "rejected")
                            WHEN "project" THEN project_target.status NOT IN ("draft", "rejected")
                            WHEN "property" THEN property_target.status NOT IN ("draft", "rejected")
                            WHEN "listing" THEN listing_target.status NOT IN ("draft", "rejected")
                            ELSE 1
                        END
                    ) AS locked_count
                 FROM media_attachments ma
                 INNER JOIN media_assets m ON m.id = ma.media_asset_id
                 LEFT JOIN companies company_target
                    ON ma.entity_type = "company" AND company_target.id = ma.entity_id
                 LEFT JOIN projects project_target
                    ON ma.entity_type = "project" AND project_target.id = ma.entity_id
                 LEFT JOIN properties property_target
                    ON ma.entity_type = "property" AND property_target.id = ma.entity_id
                 LEFT JOIN listings listing_target
                    ON ma.entity_type = "listing" AND listing_target.id = ma.entity_id
                 LEFT JOIN properties listing_property
                    ON listing_property.id = listing_target.property_id
                 WHERE EXISTS (
                    SELECT 1
                    FROM company_members viewer
                    WHERE viewer.user_id = :user_id
                      AND viewer.status = "active"
                      AND viewer.company_id = COALESCE(
                        company_target.id,
                        project_target.company_id,
                        property_target.company_id,
                        listing_property.company_id
                      )
                 )'
            );
            $statement->execute(['user_id' => $userId]);
        } else {
            return [];
        }

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return [];
        }

        $result = [];
        foreach ($row as $key => $value) {
            $key = str_ends_with((string) $key, '_count')
                ? substr((string) $key, 0, -6)
                : (string) $key;
            $result[$key] = (int) ($value ?? 0);
        }

        return $result;
    }

    /** @return array<string,int> */
    public function developerCustomerOperationsSummary(int $userId, string $section): array
    {
        $pdo = Database::connection();

        $sql = match ($section) {
            'inquiries' => 'SELECT
                COUNT(*) AS total,
                SUM(i.status = "new") AS new_count,
                SUM(i.status = "read") AS read_count,
                SUM(i.status = "replied") AS replied_count,
                SUM(i.status = "closed") AS closed_count,
                SUM(EXISTS(
                    SELECT 1 FROM leads ld
                    WHERE ld.inquiry_id = i.id AND ld.deleted_at IS NULL
                )) AS converted_count
             FROM inquiries i
             WHERE EXISTS (
                SELECT 1 FROM company_members cm
                WHERE cm.company_id = i.company_id
                  AND cm.user_id = :user_id
                  AND cm.status = "active"
             )',
            'viewings' => 'SELECT
                COUNT(*) AS total,
                SUM(v.status = "pending") AS pending_count,
                SUM(v.status = "confirmed") AS confirmed_count,
                SUM(v.status = "rescheduled") AS rescheduled_count,
                SUM(v.status = "completed") AS completed_count,
                SUM(v.status = "declined") AS declined_count,
                SUM(v.status = "cancelled") AS cancelled_count
             FROM viewing_requests v
             WHERE EXISTS (
                SELECT 1 FROM company_members cm
                WHERE cm.company_id = v.company_id
                  AND cm.user_id = :user_id
                  AND cm.status = "active"
             )',
            'leads' => 'SELECT
                COUNT(*) AS total,
                SUM(l.stage = "new") AS new_count,
                SUM(l.stage = "contacted") AS contacted_count,
                SUM(l.stage = "qualified") AS qualified_count,
                SUM(l.stage = "viewing") AS viewing_count,
                SUM(l.stage = "negotiation") AS negotiation_count,
                SUM(l.stage = "won") AS won_count,
                SUM(l.stage = "lost") AS lost_count
             FROM leads l
             WHERE l.deleted_at IS NULL
               AND EXISTS (
                    SELECT 1 FROM company_members cm
                    WHERE cm.company_id = l.company_id
                      AND cm.user_id = :user_id
                      AND cm.status = "active"
               )',
            default => null,
        };

        if ($sql === null) {
            return [];
        }

        $statement = $pdo->prepare($sql);
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return [];
        }

        $result = [];
        foreach ($row as $key => $value) {
            $key = str_ends_with((string) $key, '_count')
                ? substr((string) $key, 0, -6)
                : (string) $key;
            $result[$key] = (int) ($value ?? 0);
        }

        return $result;
    }

    /** @return array<string,int> */
    public function developerPortfolioSummary(int $userId, string $section): array
    {
        $definition = match ($section) {
            'company' => ['table' => 'companies', 'alias' => 'c', 'company' => 'c.id', 'deleted' => 'c.deleted_at'],
            'projects' => ['table' => 'projects', 'alias' => 'p', 'company' => 'p.company_id', 'deleted' => 'p.deleted_at'],
            'properties' => ['table' => 'properties', 'alias' => 'p', 'company' => 'p.company_id', 'deleted' => 'p.deleted_at'],
            'listings' => ['table' => 'listings', 'alias' => 'l', 'company' => 'prop.company_id', 'deleted' => 'l.deleted_at'],
            default => null,
        };

        if ($definition === null) {
            return [];
        }

        $pdo = Database::connection();
        $alias = $definition['alias'];
        $join = $section === 'listings'
            ? ' INNER JOIN properties prop ON prop.id = l.property_id AND prop.deleted_at IS NULL'
            : '';
        $sql = 'SELECT ' . $alias . '.status AS status, COUNT(*) AS total'
            . ' FROM ' . $definition['table'] . ' ' . $alias
            . $join
            . ' INNER JOIN company_members cm ON cm.company_id = ' . $definition['company']
            . ' WHERE cm.user_id = :user_id'
            . ' AND cm.status = "active"'
            . ' AND ' . $definition['deleted'] . ' IS NULL'
            . ' GROUP BY ' . $alias . '.status';

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $result = ['total' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $status = (string) $row['status'];
            $count = (int) $row['total'];
            $result[$status] = $count;
            $result['total'] += $count;
        }

        return $result;
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
            'users' => $this->count(
                'SELECT COUNT(*) FROM users'
            ),
            'security_events' => $this->count(
                'SELECT COUNT(*) FROM security_audit_events'
            ),
        ];
    }

    /** @return array<string,int> */
    public function adminMarketplaceSummary(string $section): array
    {
        $sql = match ($section) {
            'approvals' => 'SELECT
                (SELECT COUNT(*) FROM companies WHERE status = "pending" AND deleted_at IS NULL) AS companies,
                (SELECT COUNT(*) FROM projects WHERE status = "pending" AND deleted_at IS NULL) AS projects,
                (SELECT COUNT(*) FROM properties WHERE status = "pending" AND deleted_at IS NULL) AS properties,
                (SELECT COUNT(*) FROM listings WHERE status = "pending" AND deleted_at IS NULL) AS listings',
            'companies' => 'SELECT COUNT(*) AS total,
                COALESCE(SUM(status = "pending"), 0) AS pending,
                COALESCE(SUM(status = "approved"), 0) AS approved,
                COALESCE(SUM(status = "suspended"), 0) AS suspended
                FROM companies WHERE deleted_at IS NULL',
            'projects' => 'SELECT COUNT(*) AS total,
                COALESCE(SUM(status = "pending"), 0) AS pending,
                COALESCE(SUM(status = "published"), 0) AS published,
                COALESCE(SUM(completion_status = "completed"), 0) AS completed
                FROM projects WHERE deleted_at IS NULL',
            'properties' => 'SELECT COUNT(*) AS total,
                COALESCE(SUM(status = "pending"), 0) AS pending,
                COALESCE(SUM(status = "approved"), 0) AS approved,
                COALESCE(SUM(status = "archived"), 0) AS archived
                FROM properties WHERE deleted_at IS NULL',
            'listings' => 'SELECT COUNT(*) AS total,
                COALESCE(SUM(status = "pending"), 0) AS pending,
                COALESCE(SUM(status = "published"), 0) AS published,
                COALESCE(SUM(status = "paused"), 0) AS paused
                FROM listings WHERE deleted_at IS NULL',
            'catalog' => 'SELECT
                (SELECT COUNT(*) FROM locations) AS locations,
                (SELECT COUNT(*) FROM locations WHERE status = "active") AS active_locations,
                (SELECT COUNT(*) FROM amenities) AS amenities,
                (SELECT COUNT(*) FROM amenities WHERE status = "active") AS active_amenities',
            'media' => 'SELECT COUNT(*) AS total,
                COALESCE(SUM(m.mime_type LIKE "image/%"), 0) AS images,
                COALESCE(SUM(m.mime_type = "application/pdf"), 0) AS pdfs,
                COALESCE(SUM(CASE WHEN NOT EXISTS (
                    SELECT 1 FROM media_attachments ma WHERE ma.media_asset_id = m.id
                ) THEN 1 ELSE 0 END), 0) AS orphaned
                FROM media_assets m',
            'inquiries' => 'SELECT COUNT(*) AS total,
                COALESCE(SUM(status = "new"), 0) AS new,
                COALESCE(SUM(status = "read"), 0) AS `read`,
                COALESCE(SUM(status = "replied"), 0) AS replied,
                COALESCE(SUM(status = "closed"), 0) AS closed,
                COALESCE(SUM(status = "spam"), 0) AS spam,
                (SELECT COUNT(*) FROM leads l WHERE l.inquiry_id IS NOT NULL AND l.deleted_at IS NULL) AS converted
                FROM inquiries',
            'leads' => 'SELECT COUNT(*) AS total,
                COALESCE(SUM(stage NOT IN ("won", "lost")), 0) AS active,
                COALESCE(SUM(stage = "new"), 0) AS new,
                COALESCE(SUM(stage = "contacted"), 0) AS contacted,
                COALESCE(SUM(stage = "qualified"), 0) AS qualified,
                COALESCE(SUM(stage = "viewing"), 0) AS viewing,
                COALESCE(SUM(stage = "negotiation"), 0) AS negotiation,
                COALESCE(SUM(stage = "won"), 0) AS won,
                COALESCE(SUM(stage = "lost"), 0) AS lost
                FROM leads WHERE deleted_at IS NULL',
            'users' => 'SELECT COUNT(*) AS total,
                COALESCE(SUM(status = "active"), 0) AS active,
                COALESCE(SUM(status = "suspended"), 0) AS suspended,
                COALESCE(SUM(status = "deactivated"), 0) AS deactivated,
                COALESCE(SUM(email_verified_at IS NOT NULL), 0) AS verified,
                (SELECT COUNT(DISTINCT ur.user_id)
                 FROM user_roles ur
                 INNER JOIN roles r ON r.id = ur.role_id
                 WHERE r.slug = "administrator") AS administrators,
                (SELECT COUNT(DISTINCT cm.user_id)
                 FROM company_members cm
                 INNER JOIN companies c ON c.id = cm.company_id
                 WHERE cm.status = "active"
                   AND c.deleted_at IS NULL) AS company_members
                FROM users',
            'audit' => 'SELECT COUNT(*) AS total,
                COALESCE(SUM(event_type LIKE "auth.%"), 0) AS auth_events,
                COALESCE(SUM(event_type LIKE "account.%"), 0) AS account_events,
                COALESCE(SUM(event_type LIKE "admin.%"), 0) AS admin_events,
                COALESCE(SUM(created_at >= (CURRENT_TIMESTAMP - INTERVAL 1 DAY)), 0) AS recent_24h
                FROM security_audit_events',
            default => null,
        };

        if ($sql === null) {
            return [];
        }

        $row = Database::connection()->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
        $summary = [];

        foreach ($row as $key => $value) {
            $summary[(string) $key] = (int) $value;
        }

        return $summary;
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
                'SELECT c.id, c.name, c.company_type, c.status, c.email, c.phone,
                        c.location_id, c.verified_at, c.updated_at, loc.name AS location_name,
                        (SELECT COUNT(*) FROM projects pr WHERE pr.company_id = c.id AND pr.deleted_at IS NULL) AS projects_count,
                        (SELECT COUNT(*) FROM properties prop WHERE prop.company_id = c.id AND prop.deleted_at IS NULL) AS properties_count
                 FROM companies c
                 LEFT JOIN locations loc ON loc.id = c.location_id
                 WHERE c.deleted_at IS NULL
                   AND CONCAT_WS(" ", c.name, c.email) LIKE :search
                 ORDER BY c.updated_at DESC',
                'SELECT COUNT(*)
                 FROM companies c
                 WHERE c.deleted_at IS NULL
                   AND CONCAT_WS(" ", c.name, c.email) LIKE :search',
                ['search' => $search],
            ],
            'projects' => [
                'SELECT p.id, p.name, p.project_type, p.completion_status,
                        p.location_id, p.status, p.updated_at, c.name AS company_name,
                        loc.name AS location_name,
                        (SELECT COUNT(*) FROM properties prop WHERE prop.project_id = p.id AND prop.deleted_at IS NULL) AS properties_count
                 FROM projects p
                 INNER JOIN companies c ON c.id = p.company_id
                 LEFT JOIN locations loc ON loc.id = p.location_id
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
                        p.location_id, p.status, p.updated_at, c.name AS company_name,
                        loc.name AS location_name,
                        (SELECT COUNT(*) FROM listings li WHERE li.property_id = p.id AND li.deleted_at IS NULL) AS listings_count
                 FROM properties p
                 LEFT JOIN companies c ON c.id = p.company_id
                 LEFT JOIN locations loc ON loc.id = p.location_id
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
                        p.property_type, p.location_id, c.name AS company_name, loc.name AS location_name,
                        l.transaction_type, l.status, l.price, l.currency, l.expires_at,
                        l.updated_at
                 FROM listings l
                 INNER JOIN properties p ON p.id = l.property_id
                 LEFT JOIN companies c ON c.id = p.company_id
                 LEFT JOIN locations loc ON loc.id = p.location_id
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
                        i.status, i.source, i.assigned_user_id, i.created_at, i.updated_at,
                        c.name AS company_name,
                        p.title AS property_title, p.reference_code AS property_reference,
                        p.property_type, p.location_id,
                        listing.transaction_type, listing.price, listing.currency,
                        assigned.name AS assigned_user_name,
                        converted_lead.id AS converted_lead_id, converted_lead.stage AS converted_lead_stage
                 FROM inquiries i
                 LEFT JOIN companies c ON c.id = i.company_id
                 LEFT JOIN properties p ON p.id = i.property_id AND p.deleted_at IS NULL
                 LEFT JOIN listings listing ON listing.id = i.listing_id AND listing.deleted_at IS NULL
                 LEFT JOIN users assigned ON assigned.id = i.assigned_user_id
                 LEFT JOIN leads converted_lead ON converted_lead.inquiry_id = i.id AND converted_lead.deleted_at IS NULL
                 WHERE CONCAT_WS(
                    " ", i.contact_name, i.contact_email, i.contact_phone,
                    c.name, p.title, p.reference_code, assigned.name
                 ) LIKE :search
                 ORDER BY i.created_at DESC',
                'SELECT COUNT(*)
                 FROM inquiries i
                 LEFT JOIN companies c ON c.id = i.company_id
                 LEFT JOIN properties p ON p.id = i.property_id AND p.deleted_at IS NULL
                 LEFT JOIN users assigned ON assigned.id = i.assigned_user_id
                 WHERE CONCAT_WS(
                    " ", i.contact_name, i.contact_email, i.contact_phone,
                    c.name, p.title, p.reference_code, assigned.name
                 ) LIKE :search',
                ['search' => $search],
            ],
            'leads' => [
                'SELECT l.id, l.contact_name, l.contact_email, l.contact_phone,
                        l.stage, l.transaction_type, l.property_type,
                        l.preferred_location_id, l.budget_min, l.budget_max,
                        l.currency, l.assigned_user_id, l.last_contact_at,
                        l.next_follow_up_at, l.updated_at,
                        c.name AS company_name,
                        assigned.name AS assigned_user_name,
                        loc.name AS preferred_location_name,
                        inquiry.id AS source_inquiry_id,
                        p.title AS source_property_title,
                        p.reference_code AS source_property_reference,
                        listing.transaction_type AS source_listing_offer,
                        listing.price AS source_listing_price,
                        listing.currency AS source_listing_currency
                 FROM leads l
                 INNER JOIN companies c ON c.id = l.company_id
                 LEFT JOIN users assigned ON assigned.id = l.assigned_user_id
                 LEFT JOIN locations loc ON loc.id = l.preferred_location_id
                 LEFT JOIN inquiries inquiry ON inquiry.id = l.inquiry_id
                 LEFT JOIN properties p ON p.id = inquiry.property_id AND p.deleted_at IS NULL
                 LEFT JOIN listings listing ON listing.id = inquiry.listing_id AND listing.deleted_at IS NULL
                 WHERE l.deleted_at IS NULL
                   AND CONCAT_WS(
                    " ", l.contact_name, l.contact_email, l.contact_phone,
                    c.name, p.title, p.reference_code, assigned.name, loc.name
                   ) LIKE :search
                 ORDER BY l.updated_at DESC',
                'SELECT COUNT(*)
                 FROM leads l
                 INNER JOIN companies c ON c.id = l.company_id
                 LEFT JOIN users assigned ON assigned.id = l.assigned_user_id
                 LEFT JOIN locations loc ON loc.id = l.preferred_location_id
                 LEFT JOIN inquiries inquiry ON inquiry.id = l.inquiry_id
                 LEFT JOIN properties p ON p.id = inquiry.property_id AND p.deleted_at IS NULL
                 WHERE l.deleted_at IS NULL
                   AND CONCAT_WS(
                    " ", l.contact_name, l.contact_email, l.contact_phone,
                    c.name, p.title, p.reference_code, assigned.name, loc.name
                   ) LIKE :search',
                ['search' => $search],
            ],
            'catalog' => [
                'SELECT *
                 FROM (
                    SELECT "location" AS entity_type, l.id, l.name AS title,
                           l.status, l.type AS context, parent.name AS parent_name,
                           (SELECT COUNT(*) FROM locations child
                            WHERE child.parent_id = l.id AND child.status = "active") AS children_count,
                           (
                               (SELECT COUNT(*) FROM companies c WHERE c.location_id = l.id AND c.deleted_at IS NULL)
                               + (SELECT COUNT(*) FROM projects p WHERE p.location_id = l.id AND p.deleted_at IS NULL)
                               + (SELECT COUNT(*) FROM properties p WHERE p.location_id = l.id AND p.deleted_at IS NULL)
                               + (SELECT COUNT(*) FROM leads lead_row WHERE lead_row.preferred_location_id = l.id AND lead_row.deleted_at IS NULL)
                               + (SELECT COUNT(*) FROM saved_searches saved WHERE saved.location_id = l.id)
                           ) AS usage_count,
                           l.updated_at
                    FROM locations l
                    LEFT JOIN locations parent ON parent.id = l.parent_id
                    UNION ALL
                    SELECT "amenity" AS entity_type, a.id, a.name AS title,
                           a.status, a.category AS context, NULL AS parent_name,
                           0 AS children_count,
                           (
                               (SELECT COUNT(*) FROM project_amenities pa
                                INNER JOIN projects project_row ON project_row.id = pa.project_id
                                WHERE pa.amenity_id = a.id AND project_row.deleted_at IS NULL)
                               + (SELECT COUNT(*) FROM property_amenities pa
                                  INNER JOIN properties property_row ON property_row.id = pa.property_id
                                  WHERE pa.amenity_id = a.id AND property_row.deleted_at IS NULL)
                           ) AS usage_count,
                           a.updated_at
                    FROM amenities a
                 ) catalog
                 WHERE CONCAT_WS(" ", catalog.title, catalog.context, catalog.parent_name) LIKE :search
                 ORDER BY catalog.entity_type ASC, catalog.title ASC',
                'SELECT COUNT(*) FROM locations',
                ['search' => $search],
            ],
            'media' => [
                'SELECT *
                 FROM (
                    SELECT m.id, m.id AS media_id, m.uuid, m.original_name,
                           m.mime_type, m.size_bytes, m.width_px, m.height_px,
                           m.disk, m.path, m.alt_text, m.checksum_sha256,
                           m.created_at, COALESCE(u.name, "System") AS uploaded_by,
                           (SELECT COUNT(*) FROM media_attachments ma
                            WHERE ma.media_asset_id = m.id) AS attachment_count,
                           (SELECT COUNT(*) FROM media_attachments ma
                            WHERE ma.media_asset_id = m.id AND ma.is_primary = 1) AS primary_count,
                           CASE WHEN EXISTS (
                               SELECT 1 FROM media_attachments ma WHERE ma.media_asset_id = m.id
                           ) THEN "attached" ELSE "orphan" END AS attachment_state,
                           (SELECT ma.entity_type FROM media_attachments ma
                            WHERE ma.media_asset_id = m.id
                            ORDER BY ma.is_primary DESC, ma.id ASC LIMIT 1) AS target_type,
                           (SELECT ma.entity_id FROM media_attachments ma
                            WHERE ma.media_asset_id = m.id
                            ORDER BY ma.is_primary DESC, ma.id ASC LIMIT 1) AS target_id,
                           (SELECT ma.collection FROM media_attachments ma
                            WHERE ma.media_asset_id = m.id
                            ORDER BY ma.is_primary DESC, ma.id ASC LIMIT 1) AS collection,
                           (SELECT CASE ma.entity_type
                                WHEN "company" THEN (SELECT company_row.name FROM companies company_row WHERE company_row.id = ma.entity_id)
                                WHEN "project" THEN (SELECT project_row.name FROM projects project_row WHERE project_row.id = ma.entity_id)
                                WHEN "property" THEN (SELECT property_row.title FROM properties property_row WHERE property_row.id = ma.entity_id)
                                WHEN "listing" THEN (SELECT property_row.title
                                     FROM listings listing_row
                                     INNER JOIN properties property_row ON property_row.id = listing_row.property_id
                                     WHERE listing_row.id = ma.entity_id)
                                ELSE NULL
                            END
                            FROM media_attachments ma
                            WHERE ma.media_asset_id = m.id
                            ORDER BY ma.is_primary DESC, ma.id ASC LIMIT 1) AS target_title
                    FROM media_assets m
                    LEFT JOIN users u ON u.id = m.created_by_user_id
                 ) media_rows
                 WHERE CONCAT_WS(
                    " ", media_rows.original_name, media_rows.alt_text,
                    media_rows.uploaded_by, media_rows.target_title,
                    media_rows.target_type, media_rows.collection
                 ) LIKE :search
                 ORDER BY media_rows.created_at DESC',
                'SELECT COUNT(*) FROM media_assets',
                ['search' => $search],
            ],
            'users' => [
                'SELECT u.id, u.name, u.email, u.status,
                        CASE WHEN u.email_verified_at IS NULL
                             THEN "unverified" ELSE "verified" END AS email_state,
                        u.email_verified_at, u.last_login_at, u.created_at, u.updated_at,
                        COALESCE(role_summary.roles, "user") AS roles,
                        CASE
                            WHEN COALESCE(role_summary.is_administrator, 0) = 1 THEN "administrator"
                            WHEN COALESCE(role_summary.is_developer, 0) = 1 THEN "developer"
                            ELSE "user"
                        END AS account_role,
                        COALESCE(company_access.company_count, 0) AS company_count,
                        COALESCE(company_access.owned_company_count, 0) AS owned_company_count,
                        COALESCE(company_access.company_names, "") AS company_names,
                        (
                            SELECT COUNT(*)
                            FROM security_audit_events user_events
                            WHERE user_events.actor_user_id = u.id
                               OR user_events.subject_user_id = u.id
                        ) AS security_events_count
                 FROM users u
                 LEFT JOIN (
                    SELECT ur.user_id,
                           GROUP_CONCAT(DISTINCT r.slug ORDER BY r.slug SEPARATOR ", ") AS roles,
                           MAX(r.slug = "administrator") AS is_administrator,
                           MAX(r.slug = "developer") AS is_developer
                    FROM user_roles ur
                    INNER JOIN roles r ON r.id = ur.role_id
                    GROUP BY ur.user_id
                 ) role_summary ON role_summary.user_id = u.id
                 LEFT JOIN (
                    SELECT cm.user_id,
                           COUNT(DISTINCT cm.company_id) AS company_count,
                           COUNT(DISTINCT CASE WHEN cm.member_role = "owner" THEN cm.company_id END) AS owned_company_count,
                           GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ", ") AS company_names
                    FROM company_members cm
                    INNER JOIN companies c ON c.id = cm.company_id
                    WHERE cm.status = "active"
                      AND c.deleted_at IS NULL
                    GROUP BY cm.user_id
                 ) company_access ON company_access.user_id = u.id
                 WHERE CONCAT_WS(" ", u.name, u.email, company_access.company_names) LIKE :search
                 ORDER BY u.updated_at DESC',
                'SELECT COUNT(*)
                 FROM users u
                 WHERE CONCAT_WS(" ", u.name, u.email) LIKE :search',
                ['search' => $search],
            ],
            'audit' => [
                'SELECT e.id, e.event_type,
                        SUBSTRING_INDEX(e.event_type, ".", 1) AS event_family,
                        e.actor_user_id, e.subject_user_id,
                        CASE
                            WHEN actor.id IS NULL THEN "System"
                            ELSE CONCAT(actor.name, " · ", actor.email)
                        END AS actor,
                        CASE
                            WHEN subject.id IS NULL THEN "—"
                            ELSE CONCAT(subject.name, " · ", subject.email)
                        END AS subject,
                        actor.status AS actor_status,
                        subject.status AS subject_status,
                        CASE
                            WHEN e.ip_hash IS NOT NULL OR e.user_agent_hash IS NOT NULL
                            THEN "hashed"
                            ELSE "not recorded"
                        END AS client_state,
                        e.created_at
                 FROM security_audit_events e
                 LEFT JOIN users actor ON actor.id = e.actor_user_id
                 LEFT JOIN users subject ON subject.id = e.subject_user_id
                 WHERE (
                    CONVERT(
                        CONCAT_WS(
                            " ",
                            e.event_type,
                            actor.name,
                            actor.email,
                            subject.name,
                            subject.email,
                            COALESCE(CAST(e.metadata_json AS CHAR), "")
                        )
                        USING utf8mb4
                    ) COLLATE utf8mb4_unicode_ci
                 ) LIKE :search
                 ORDER BY e.created_at DESC',
                'SELECT COUNT(*)
                 FROM security_audit_events e
                 LEFT JOIN users actor ON actor.id = e.actor_user_id
                 LEFT JOIN users subject ON subject.id = e.subject_user_id
                 WHERE CONCAT_WS(" ", e.event_type, actor.email, subject.email) LIKE :search',
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
                'sorts' => [
                    'name_asc' => 'title ASC',
                    'name_desc' => 'title DESC',
                    'usage_desc' => 'usage_count DESC',
                    'updated_desc' => 'updated_at DESC',
                ],
            ],
            'admin:media' => [
                'filters' => ['status' => 'attachment_state', 'type' => 'mime_type'],
                'sorts' => [
                    'updated_desc' => 'created_at DESC',
                    'updated_asc' => 'created_at ASC',
                    'name_asc' => 'original_name ASC',
                    'size_desc' => 'size_bytes DESC',
                    'size_asc' => 'size_bytes ASC',
                ],
            ],
            'admin:users' => [
                'filters' => ['status' => 'status', 'type' => 'account_role'],
                'sorts' => [
                    'updated_desc' => 'updated_at DESC',
                    'updated_asc' => 'updated_at ASC',
                    'name_asc' => 'name ASC',
                    'name_desc' => 'name DESC',
                    'company_desc' => 'company_count DESC',
                ],
            ],
            'admin:audit' => [
                'filters' => ['type' => 'event_family'],
                'sorts' => [
                    'updated_desc' => 'created_at DESC',
                    'updated_asc' => 'created_at ASC',
                    'name_asc' => 'event_type ASC',
                ],
            ],
        ];

        $definition = $definitions[$key] ?? ['filters' => [], 'sorts' => []];
        $baseSql = $this->stripTopLevelOrderBy($sql);

        if ($baseSql === '') {
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

    private function stripTopLevelOrderBy(string $sql): string
    {
        $sql = trim($sql);
        $length = strlen($sql);
        $depth = 0;
        $quoteCode = null;
        $orderByOffset = null;

        for ($index = 0; $index < $length; $index++) {
            $character = $sql[$index];
            $code = ord($character);

            if ($quoteCode !== null) {
                if ($code === 92) {
                    $index++;
                    continue;
                }

                if ($code === $quoteCode) {
                    if (
                        $index + 1 < $length
                        && ord($sql[$index + 1]) === $quoteCode
                    ) {
                        $index++;
                        continue;
                    }

                    $quoteCode = null;
                }

                continue;
            }

            if (in_array($code, [39, 34, 96], true)) {
                $quoteCode = $code;
                continue;
            }

            if ($character === '(') {
                $depth++;
                continue;
            }

            if ($character === ')') {
                $depth = max(0, $depth - 1);
                continue;
            }

            if ($depth !== 0) {
                continue;
            }

            if (
                strncasecmp(substr($sql, $index, 8), 'ORDER BY', 8) === 0
                && ($index === 0 || ctype_space($sql[$index - 1]))
                && (
                    $index + 8 >= $length
                    || ctype_space($sql[$index + 8])
                )
            ) {
                $orderByOffset = $index;
            }
        }

        if ($orderByOffset === null) {
            return $sql;
        }

        return rtrim(substr($sql, 0, $orderByOffset));
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
