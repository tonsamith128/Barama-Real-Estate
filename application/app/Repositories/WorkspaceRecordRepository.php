<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use InvalidArgumentException;
use PDO;

final class WorkspaceRecordRepository
{
    /** @return array{record:array<string,mixed>,history:array<int,array<string,mixed>>,media:array<int,array<string,mixed>>}|null */
    public function developerRecord(int $userId, string $resource, int $id): ?array
    {
        $sql = match ($resource) {
            'company' => 'SELECT c.*, loc.name AS location_name, cm.member_role AS viewer_role
                          FROM companies c
                          INNER JOIN company_members cm
                             ON cm.company_id = c.id
                            AND cm.user_id = :user_id
                            AND cm.status = "active"
                          LEFT JOIN locations loc ON loc.id = c.location_id
                          WHERE c.id = :id AND c.deleted_at IS NULL LIMIT 1',
            'project' => 'SELECT p.*, c.name AS company_name, c.slug AS company_slug,
                                 loc.name AS location_name, cm.member_role AS viewer_role
                          FROM projects p
                          INNER JOIN companies c ON c.id = p.company_id
                          INNER JOIN company_members cm
                             ON cm.company_id = p.company_id
                            AND cm.user_id = :user_id
                            AND cm.status = "active"
                          LEFT JOIN locations loc ON loc.id = p.location_id
                          WHERE p.id = :id AND p.deleted_at IS NULL LIMIT 1',
            'property' => 'SELECT p.*, c.name AS company_name, c.slug AS company_slug,
                                  pr.name AS project_name, pr.slug AS project_slug,
                                  loc.name AS location_name, cm.member_role AS viewer_role
                           FROM properties p
                           INNER JOIN companies c ON c.id = p.company_id
                           INNER JOIN company_members cm
                              ON cm.company_id = p.company_id
                             AND cm.user_id = :user_id
                             AND cm.status = "active"
                           LEFT JOIN projects pr ON pr.id = p.project_id AND pr.deleted_at IS NULL
                           LEFT JOIN locations loc ON loc.id = p.location_id
                           WHERE p.id = :id AND p.deleted_at IS NULL LIMIT 1',
            'listing' => 'SELECT l.*, p.company_id, p.title AS property_title,
                                 p.reference_code, p.property_type,
                                 p.status AS property_status,
                                 c.name AS company_name, c.slug AS company_slug,
                                 loc.name AS location_name, cm.member_role AS viewer_role
                          FROM listings l
                          INNER JOIN properties p ON p.id = l.property_id
                          INNER JOIN companies c ON c.id = p.company_id
                          INNER JOIN company_members cm
                             ON cm.company_id = p.company_id
                            AND cm.user_id = :user_id
                            AND cm.status = "active"
                          LEFT JOIN locations loc ON loc.id = p.location_id
                          WHERE l.id = :id
                            AND l.deleted_at IS NULL
                            AND p.deleted_at IS NULL
                          LIMIT 1',
            'inquiry' => 'SELECT i.*, c.name AS company_name,
                                 p.title AS property_title, p.reference_code,
                                 p.property_type, p.location_id,
                                 loc.name AS location_name,
                                 l.slug AS listing_slug, l.transaction_type,
                                 l.price, l.currency,
                                 assigned.name AS assigned_user_name,
                                 cm.member_role AS viewer_role,
                                 (SELECT MIN(ld.id) FROM leads ld WHERE ld.inquiry_id = i.id AND ld.deleted_at IS NULL) AS converted_lead_id,
                                 (SELECT ld.stage FROM leads ld WHERE ld.inquiry_id = i.id AND ld.deleted_at IS NULL ORDER BY ld.id ASC LIMIT 1) AS converted_lead_stage
                          FROM inquiries i
                          INNER JOIN companies c ON c.id = i.company_id
                          INNER JOIN company_members cm
                             ON cm.company_id = i.company_id
                            AND cm.user_id = :user_id
                            AND cm.status = "active"
                          LEFT JOIN properties p ON p.id = i.property_id
                          LEFT JOIN locations loc ON loc.id = p.location_id
                          LEFT JOIN listings l ON l.id = i.listing_id
                          LEFT JOIN users assigned ON assigned.id = i.assigned_user_id
                          WHERE i.id = :id LIMIT 1',
            'viewing' => 'SELECT v.*, c.name AS company_name,
                                 l.slug AS listing_slug, l.transaction_type,
                                 l.price, l.currency,
                                 p.title AS property_title, p.reference_code,
                                 p.property_type, p.location_id,
                                 loc.name AS location_name,
                                 u.name AS customer_name, u.email AS customer_email,
                                 cm.member_role AS viewer_role
                          FROM viewing_requests v
                          INNER JOIN companies c ON c.id = v.company_id
                          INNER JOIN company_members cm
                             ON cm.company_id = v.company_id
                            AND cm.user_id = :user_id
                            AND cm.status = "active"
                          INNER JOIN listings l ON l.id = v.listing_id
                          INNER JOIN properties p ON p.id = l.property_id
                          LEFT JOIN locations loc ON loc.id = p.location_id
                          INNER JOIN users u ON u.id = v.user_id
                          WHERE v.id = :id LIMIT 1',
            'lead' => 'SELECT l.*, c.name AS company_name,
                              u.name AS assigned_user_name,
                              loc.name AS preferred_location_name,
                              inquiry.id AS source_inquiry_id,
                              p.title AS source_property_title,
                              p.reference_code AS source_property_reference,
                              listing.slug AS source_listing_slug,
                              cm.member_role AS viewer_role
                       FROM leads l
                       INNER JOIN companies c ON c.id = l.company_id
                       INNER JOIN company_members cm
                          ON cm.company_id = l.company_id
                         AND cm.user_id = :user_id
                         AND cm.status = "active"
                       LEFT JOIN users u ON u.id = l.assigned_user_id
                       LEFT JOIN locations loc ON loc.id = l.preferred_location_id
                       LEFT JOIN inquiries inquiry ON inquiry.id = l.inquiry_id
                       LEFT JOIN properties p ON p.id = inquiry.property_id
                       LEFT JOIN listings listing ON listing.id = inquiry.listing_id
                       WHERE l.id = :id AND l.deleted_at IS NULL LIMIT 1',
            default => throw new InvalidArgumentException('Unsupported workspace resource.'),
        };

        $statement = Database::connection()->prepare($sql);
        $statement->execute(['user_id' => $userId, 'id' => $id]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($record)) {
            return null;
        }

        return [
            'record' => $record,
            'history' => $this->history($resource, $id),
            'media' => in_array($resource, ['company', 'project', 'property', 'listing'], true)
                ? $this->media($resource, $id)
                : [],
        ];
    }

    /** @return array{record:array<string,mixed>,history:array<int,array<string,mixed>>,media:array<int,array<string,mixed>>}|null */
    public function adminRecord(string $resource, int $id): ?array
    {
        $sql = match ($resource) {
            'company' => 'SELECT c.*, loc.name AS location_name,
                                 (SELECT COUNT(*) FROM company_members cm
                                  WHERE cm.company_id = c.id AND cm.status = "active") AS members_count,
                                 (SELECT COUNT(*) FROM projects pr
                                  WHERE pr.company_id = c.id AND pr.deleted_at IS NULL) AS projects_count,
                                 (SELECT COUNT(*) FROM properties p
                                  WHERE p.company_id = c.id AND p.deleted_at IS NULL) AS properties_count,
                                 (SELECT COUNT(*)
                                  FROM listings l
                                  INNER JOIN properties p ON p.id = l.property_id
                                  WHERE p.company_id = c.id
                                    AND p.deleted_at IS NULL
                                    AND l.status = "published"
                                    AND l.deleted_at IS NULL) AS published_listings_count
                          FROM companies c
                          LEFT JOIN locations loc ON loc.id = c.location_id
                          WHERE c.id = :id AND c.deleted_at IS NULL LIMIT 1',
            'project' => 'SELECT p.*, c.name AS company_name, c.slug AS company_slug,
                                 c.status AS company_status, loc.name AS location_name,
                                 (SELECT COUNT(*) FROM properties prop
                                  WHERE prop.project_id = p.id AND prop.deleted_at IS NULL) AS properties_count,
                                 (SELECT COUNT(*) FROM properties prop
                                  WHERE prop.project_id = p.id
                                    AND prop.status = "approved"
                                    AND prop.deleted_at IS NULL) AS approved_properties_count,
                                 (SELECT COUNT(*) FROM media_attachments ma
                                  WHERE ma.entity_type = "project" AND ma.entity_id = p.id) AS media_count
                          FROM projects p
                          INNER JOIN companies c ON c.id = p.company_id
                          LEFT JOIN locations loc ON loc.id = p.location_id
                          WHERE p.id = :id AND p.deleted_at IS NULL LIMIT 1',
            'property' => 'SELECT p.*,
                                  c.name AS company_name,
                                  c.slug AS company_slug,
                                  c.status AS company_status,
                                  pr.name AS project_name,
                                  pr.slug AS project_slug,
                                  pr.status AS project_status,
                                  loc.name AS location_name,
                                  (SELECT COUNT(*)
                                   FROM listings li
                                   WHERE li.property_id = p.id
                                     AND li.deleted_at IS NULL) AS listings_count,
                                  (SELECT COUNT(*)
                                   FROM listings li
                                   WHERE li.property_id = p.id
                                     AND li.status = "published"
                                     AND li.deleted_at IS NULL) AS published_listings_count,
                                  (SELECT COUNT(*)
                                   FROM media_attachments ma
                                   WHERE ma.entity_type = "property"
                                     AND ma.entity_id = p.id) AS media_count
                           FROM properties p
                           LEFT JOIN companies c
                              ON c.id = p.company_id
                             AND c.deleted_at IS NULL
                           LEFT JOIN projects pr
                              ON pr.id = p.project_id
                             AND pr.deleted_at IS NULL
                           LEFT JOIN locations loc ON loc.id = p.location_id
                           WHERE p.id = :id
                             AND p.deleted_at IS NULL
                           LIMIT 1',
            'listing' => 'SELECT l.*, p.company_id, p.title AS property_title,
                                 p.reference_code, p.property_type,
                                 p.status AS property_status, p.project_id,
                                 c.name AS company_name, c.slug AS company_slug,
                                 c.status AS company_status,
                                 pr.name AS project_name, pr.status AS project_status,
                                 loc.name AS location_name,
                                 (SELECT COUNT(*) FROM media_attachments ma
                                  WHERE ma.entity_type = "listing" AND ma.entity_id = l.id) AS media_count
                          FROM listings l
                          INNER JOIN properties p ON p.id = l.property_id
                          INNER JOIN companies c ON c.id = p.company_id
                          LEFT JOIN projects pr ON pr.id = p.project_id AND pr.deleted_at IS NULL
                          LEFT JOIN locations loc ON loc.id = p.location_id
                          WHERE l.id = :id
                            AND l.deleted_at IS NULL
                            AND p.deleted_at IS NULL
                            AND c.deleted_at IS NULL
                          LIMIT 1',
            'inquiry' => 'SELECT i.*, c.name AS company_name,
                                 p.title AS property_title, p.reference_code,
                                 p.property_type, p.location_id,
                                 loc.name AS location_name,
                                 listing.slug AS listing_slug, listing.transaction_type,
                                 listing.price, listing.currency,
                                 assigned.name AS assigned_user_name,
                                 converted_lead.id AS converted_lead_id,
                                 converted_lead.stage AS converted_lead_stage
                          FROM inquiries i
                          LEFT JOIN companies c ON c.id = i.company_id
                          LEFT JOIN properties p ON p.id = i.property_id AND p.deleted_at IS NULL
                          LEFT JOIN locations loc ON loc.id = p.location_id
                          LEFT JOIN listings listing ON listing.id = i.listing_id AND listing.deleted_at IS NULL
                          LEFT JOIN users assigned ON assigned.id = i.assigned_user_id
                          LEFT JOIN leads converted_lead ON converted_lead.inquiry_id = i.id AND converted_lead.deleted_at IS NULL
                          WHERE i.id = :id LIMIT 1',
            'viewing' => 'SELECT v.*, c.name AS company_name,
                                 l.slug AS listing_slug, l.transaction_type,
                                 l.price, l.currency,
                                 p.title AS property_title, p.reference_code,
                                 p.property_type, p.location_id,
                                 loc.name AS location_name,
                                 u.name AS customer_name, u.email AS customer_email
                          FROM viewing_requests v
                          INNER JOIN companies c ON c.id = v.company_id
                          INNER JOIN listings l ON l.id = v.listing_id
                          INNER JOIN properties p ON p.id = l.property_id
                          LEFT JOIN locations loc ON loc.id = p.location_id
                          INNER JOIN users u ON u.id = v.user_id
                          WHERE v.id = :id LIMIT 1',
            'lead' => 'SELECT l.*, c.name AS company_name,
                              assigned.name AS assigned_user_name,
                              loc.name AS preferred_location_name,
                              inquiry.id AS source_inquiry_id,
                              inquiry.status AS source_inquiry_status,
                              p.title AS source_property_title,
                              p.reference_code AS source_property_reference,
                              p.property_type AS source_property_type,
                              listing.slug AS source_listing_slug,
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
                       WHERE l.id = :id AND l.deleted_at IS NULL LIMIT 1',
            'user' => 'SELECT u.id, u.uuid, u.name, u.email, u.status, u.auth_version,
                              u.email_verified_at, u.last_login_at, u.created_at, u.updated_at,
                              COALESCE(role_summary.roles, "user") AS roles,
                              CASE
                                  WHEN COALESCE(role_summary.is_administrator, 0) = 1 THEN "administrator"
                                  WHEN COALESCE(role_summary.is_developer, 0) = 1 THEN "developer"
                                  ELSE "user"
                              END AS account_role,
                              COALESCE(company_access.company_count, 0) AS company_count,
                              COALESCE(company_access.owned_company_count, 0) AS owned_company_count,
                              COALESCE(company_access.company_access, "") AS company_access,
                              (
                                  SELECT COUNT(*)
                                  FROM security_audit_events user_events
                                  WHERE user_events.actor_user_id = u.id
                                     OR user_events.subject_user_id = u.id
                              ) AS security_events_count,
                              (
                                  SELECT MAX(user_events.created_at)
                                  FROM security_audit_events user_events
                                  WHERE user_events.actor_user_id = u.id
                                     OR user_events.subject_user_id = u.id
                              ) AS last_security_event_at
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
                                 GROUP_CONCAT(
                                    DISTINCT CONCAT(c.name, " (", cm.member_role, ")")
                                    ORDER BY c.name SEPARATOR ", "
                                 ) AS company_access
                          FROM company_members cm
                          INNER JOIN companies c ON c.id = cm.company_id
                          WHERE cm.status = "active"
                            AND c.deleted_at IS NULL
                          GROUP BY cm.user_id
                       ) company_access ON company_access.user_id = u.id
                       WHERE u.id = :id
                       LIMIT 1',
            'audit' => 'SELECT e.id, e.event_type,
                               SUBSTRING_INDEX(e.event_type, ".", 1) AS event_family,
                               e.actor_user_id, e.subject_user_id,
                               e.ip_hash, e.user_agent_hash, e.metadata_json, e.created_at,
                               actor.name AS actor_name, actor.email AS actor_email,
                               actor.status AS actor_status,
                               subject.name AS subject_name, subject.email AS subject_email,
                               subject.status AS subject_status
                        FROM security_audit_events e
                        LEFT JOIN users actor ON actor.id = e.actor_user_id
                        LEFT JOIN users subject ON subject.id = e.subject_user_id
                        WHERE e.id = :id
                        LIMIT 1',
            default => throw new InvalidArgumentException('Unsupported workspace resource.'),
        };

        $statement = Database::connection()->prepare($sql);
        $statement->execute(['id' => $id]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($record)) {
            return null;
        }

        return [
            'record' => $record,
            'history' => in_array($resource, ['user', 'audit'], true)
                ? []
                : $this->history($resource, $id),
            'media' => in_array($resource, ['company', 'project', 'property', 'listing'], true)
                ? $this->media($resource, $id)
                : [],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function history(string $resource, int $id): array
    {
        $statement = Database::connection()->prepare(
            'SELECT h.*, u.name AS actor_name
             FROM entity_status_history h
             LEFT JOIN users u ON u.id = h.actor_user_id
             WHERE h.entity_type = :entity_type
               AND h.entity_id = :entity_id
             ORDER BY h.created_at DESC, h.id DESC
             LIMIT 100'
        );
        $statement->execute([
            'entity_type' => $resource,
            'entity_id' => $id,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    private function media(string $resource, int $id): array
    {
        $statement = Database::connection()->prepare(
            'SELECT ma.id AS attachment_id, ma.collection, ma.position, ma.is_primary,
                    m.id AS media_id, m.uuid, m.original_name, m.mime_type,
                    m.size_bytes, m.width_px, m.height_px, m.alt_text, m.created_at
             FROM media_attachments ma
             INNER JOIN media_assets m ON m.id = ma.media_asset_id
             WHERE ma.entity_type = :entity_type
               AND ma.entity_id = :entity_id
             ORDER BY ma.is_primary DESC, ma.collection ASC, ma.position ASC, ma.id ASC'
        );
        $statement->execute([
            'entity_type' => $resource,
            'entity_id' => $id,
        ]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
