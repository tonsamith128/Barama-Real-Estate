<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use Barama\Support\IdGenerator;
use PDO;

final class CustomerEngagementRepository
{
    /** @return array<string,mixed>|null */
    public function publicListing(int $listingId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT l.id, l.slug, l.transaction_type, l.price, l.currency,
                    l.pricing_period, l.published_at,
                    p.id AS property_id, p.company_id, p.title, p.reference_code,
                    p.property_type, p.bedrooms, p.bathrooms, p.area_value, p.area_unit,
                    p.location_id, p.address_line,
                    c.name AS company_name, c.slug AS company_slug,
                    loc.name AS location_name,
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
             WHERE l.id = :id
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
        $statement->execute(['id' => $listingId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function lockUser(int $userId): void
    {
        $statement = Database::connection()->prepare(
            'SELECT id FROM users WHERE id = :id FOR UPDATE'
        );
        $statement->execute(['id' => $userId]);

        if ($statement->fetchColumn() === false) {
            throw new \InvalidArgumentException('Customer account was not found.');
        }
    }

    public function saveListing(int $userId, int $listingId): void
    {
        $statement = Database::connection()->prepare(
            'INSERT IGNORE INTO saved_listings (user_id, listing_id)
             VALUES (:user_id, :listing_id)'
        );
        $statement->execute(['user_id' => $userId, 'listing_id' => $listingId]);
    }

    public function removeSavedListing(int $userId, int $listingId): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM saved_listings
             WHERE user_id = :user_id AND listing_id = :listing_id'
        );
        $statement->execute(['user_id' => $userId, 'listing_id' => $listingId]);
    }

    public function isSaved(int $userId, int $listingId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM saved_listings
             WHERE user_id = :user_id AND listing_id = :listing_id LIMIT 1'
        );
        $statement->execute(['user_id' => $userId, 'listing_id' => $listingId]);

        return $statement->fetchColumn() !== false;
    }

    public function addComparison(int $userId, int $listingId, int $position): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO comparison_items (user_id, listing_id, position)
             VALUES (:user_id, :listing_id, :position)
             ON DUPLICATE KEY UPDATE position = VALUES(position)'
        );
        $statement->execute([
            'user_id' => $userId,
            'listing_id' => $listingId,
            'position' => $position,
        ]);
    }

    public function removeComparison(int $userId, int $listingId): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM comparison_items
             WHERE user_id = :user_id AND listing_id = :listing_id'
        );
        $statement->execute(['user_id' => $userId, 'listing_id' => $listingId]);
    }

    public function comparisonCount(int $userId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) FROM comparison_items WHERE user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    public function nextComparisonPosition(int $userId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COALESCE(MAX(position), 0) + 1
             FROM comparison_items WHERE user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);

        return (int) $statement->fetchColumn();
    }

    public function isCompared(int $userId, int $listingId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM comparison_items
             WHERE user_id = :user_id AND listing_id = :listing_id LIMIT 1'
        );
        $statement->execute(['user_id' => $userId, 'listing_id' => $listingId]);

        return $statement->fetchColumn() !== false;
    }

    public function hasActiveViewing(int $userId, int $listingId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1
             FROM viewing_requests
             WHERE user_id = :user_id
               AND listing_id = :listing_id
               AND status IN ("pending", "confirmed", "rescheduled")
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId, 'listing_id' => $listingId]);

        return $statement->fetchColumn() !== false;
    }

    public function activeLocationExists(int $locationId): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT 1 FROM locations
             WHERE id = :id AND status = "active" LIMIT 1'
        );
        $statement->execute(['id' => $locationId]);

        return $statement->fetchColumn() !== false;
    }

    public function recordView(int $userId, int $listingId): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO recent_listing_views
                (user_id, listing_id, first_viewed_at, last_viewed_at, view_count)
             VALUES
                (:user_id, :listing_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1)
             ON DUPLICATE KEY UPDATE
                last_viewed_at = CURRENT_TIMESTAMP,
                view_count = view_count + 1'
        );
        $statement->execute(['user_id' => $userId, 'listing_id' => $listingId]);
    }

    /** @param array<string,mixed> $data */
    public function createSavedSearch(int $userId, array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO saved_searches
                (uuid, user_id, name, query_text, transaction_type, property_type,
                 location_id, bedrooms_min, price_min, price_max, currency,
                 alert_frequency, alerts_enabled)
             VALUES
                (:uuid, :user_id, :name, :query_text, :transaction_type, :property_type,
                 :location_id, :bedrooms_min, :price_min, :price_max, :currency,
                 :alert_frequency, :alerts_enabled)'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'user_id' => $userId,
            'name' => $data['name'],
            'query_text' => $data['query_text'],
            'transaction_type' => $data['transaction_type'],
            'property_type' => $data['property_type'],
            'location_id' => $data['location_id'],
            'bedrooms_min' => $data['bedrooms_min'],
            'price_min' => $data['price_min'],
            'price_max' => $data['price_max'],
            'currency' => $data['currency'],
            'alert_frequency' => $data['alert_frequency'],
            'alerts_enabled' => $data['alerts_enabled'] ? 1 : 0,
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function deleteSavedSearch(int $userId, int $searchId): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM saved_searches
             WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute(['id' => $searchId, 'user_id' => $userId]);
    }

    public function updateSavedSearchAlerts(
        int $userId,
        int $searchId,
        bool $enabled,
        string $frequency
    ): void {
        $statement = Database::connection()->prepare(
            'UPDATE saved_searches
             SET alerts_enabled = :enabled, alert_frequency = :frequency
             WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute([
            'enabled' => $enabled ? 1 : 0,
            'frequency' => $frequency,
            'id' => $searchId,
            'user_id' => $userId,
        ]);
    }

    /** @param array<string,mixed> $data */
    public function createViewingRequest(int $userId, array $data): int
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO viewing_requests
                (uuid, user_id, listing_id, company_id, preferred_date,
                 preferred_time_window, alternate_date, alternate_time_window,
                 message, status)
             VALUES
                (:uuid, :user_id, :listing_id, :company_id, :preferred_date,
                 :preferred_time_window, :alternate_date, :alternate_time_window,
                 :message, "pending")'
        );
        $statement->execute([
            'uuid' => IdGenerator::uuid(),
            'user_id' => $userId,
            'listing_id' => $data['listing_id'],
            'company_id' => $data['company_id'],
            'preferred_date' => $data['preferred_date'],
            'preferred_time_window' => $data['preferred_time_window'],
            'alternate_date' => $data['alternate_date'],
            'alternate_time_window' => $data['alternate_time_window'],
            'message' => $data['message'],
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public function cancelViewingRequest(int $userId, int $viewingId): bool
    {
        $statement = Database::connection()->prepare(
            'UPDATE viewing_requests
             SET status = "cancelled", cancelled_at = CURRENT_TIMESTAMP
             WHERE id = :id
               AND user_id = :user_id
               AND status IN ("pending", "confirmed", "rescheduled")'
        );
        $statement->execute(['id' => $viewingId, 'user_id' => $userId]);

        return $statement->rowCount() > 0;
    }


    /** @return array<string,mixed>|null */
    public function viewingById(int $viewingId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT v.*, l.slug AS listing_slug, p.title AS property_title,
                    c.name AS company_name, u.name AS customer_name,
                    u.email AS customer_email
             FROM viewing_requests v
             INNER JOIN listings l ON l.id = v.listing_id
             INNER JOIN properties p ON p.id = l.property_id
             INNER JOIN companies c ON c.id = v.company_id
             INNER JOIN users u ON u.id = v.user_id
             WHERE v.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $viewingId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function updateViewingByCompany(
        int $viewingId,
        string $expectedStatus,
        string $status,
        ?string $scheduledFor,
        ?string $companyNote
    ): bool {
        $additional = $status === 'completed'
            ? ', completed_at = CURRENT_TIMESTAMP'
            : '';

        $statement = Database::connection()->prepare(
            'UPDATE viewing_requests
             SET status = :status,
                 scheduled_for = :scheduled_for,
                 company_note = :company_note,
                 updated_at = CURRENT_TIMESTAMP'
                 . $additional .
            ' WHERE id = :id
                AND status = :expected_status'
        );
        $statement->execute([
            'status' => $status,
            'scheduled_for' => $scheduledFor,
            'company_note' => $companyNote,
            'id' => $viewingId,
            'expected_status' => $expectedStatus,
        ]);

        return $statement->rowCount() > 0;
    }

    /** @return array<string,int> */
    public function summary(int $userId): array
    {
        $pdo = Database::connection();
        $queries = [
            'saved' => 'SELECT COUNT(*) FROM saved_listings WHERE user_id = :user_id',
            'compare' => 'SELECT COUNT(*) FROM comparison_items WHERE user_id = :user_id',
            'inquiries' => 'SELECT COUNT(*) FROM inquiries WHERE user_id = :user_id',
            'viewings' => 'SELECT COUNT(*) FROM viewing_requests WHERE user_id = :user_id AND status NOT IN ("cancelled", "completed", "declined")',
            'alerts' => 'SELECT COUNT(*) FROM saved_searches WHERE user_id = :user_id AND alerts_enabled = 1',
        ];
        $result = [];

        foreach ($queries as $key => $sql) {
            $statement = $pdo->prepare($sql);
            $statement->execute(['user_id' => $userId]);
            $result[$key] = (int) $statement->fetchColumn();
        }

        return $result;
    }

    /** @return array<int,array<string,mixed>> */
    public function savedListings(int $userId, int $limit = 50): array
    {
        return $this->userListings(
            'saved_listings',
            'sl.created_at',
            'sl.user_id = :user_id',
            $userId,
            $limit,
            'sl'
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function comparisonItems(int $userId): array
    {
        return $this->userListings(
            'comparison_items',
            'ci.position',
            'ci.user_id = :user_id',
            $userId,
            4,
            'ci'
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function recentViews(int $userId, int $limit = 24): array
    {
        return $this->userListings(
            'recent_listing_views',
            'rv.last_viewed_at',
            'rv.user_id = :user_id',
            $userId,
            $limit,
            'rv'
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function inquiries(int $userId, int $limit = 100): array
    {
        $statement = Database::connection()->prepare(
            'SELECT i.id, i.status, i.subject, i.message, i.created_at, i.responded_at,
                    c.name AS company_name,
                    p.title AS property_title,
                    l.slug AS listing_slug
             FROM inquiries i
             LEFT JOIN companies c ON c.id = i.company_id
             LEFT JOIN properties p ON p.id = i.property_id
             LEFT JOIN listings l ON l.id = i.listing_id
             WHERE i.user_id = :user_id
             ORDER BY i.created_at DESC
             LIMIT :limit'
        );
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':limit', max(1, min(200, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function savedSearches(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT s.*, loc.name AS location_name
             FROM saved_searches s
             LEFT JOIN locations loc ON loc.id = s.location_id
             WHERE s.user_id = :user_id
             ORDER BY s.updated_at DESC, s.id DESC
             LIMIT 100'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function viewingRequests(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT v.*, l.slug AS listing_slug, p.title AS property_title,
                    c.name AS company_name
             FROM viewing_requests v
             INNER JOIN listings l ON l.id = v.listing_id
             INNER JOIN properties p ON p.id = l.property_id
             INNER JOIN companies c ON c.id = v.company_id
             WHERE v.user_id = :user_id
             ORDER BY v.created_at DESC
             LIMIT 100'
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    private function userListings(
        string $table,
        string $orderColumn,
        string $ownershipWhere,
        int $userId,
        int $limit,
        string $alias
    ): array {
        $allowed = [
            'saved_listings' => ['alias' => 'sl', 'order' => 'sl.created_at'],
            'comparison_items' => ['alias' => 'ci', 'order' => 'ci.position'],
            'recent_listing_views' => ['alias' => 'rv', 'order' => 'rv.last_viewed_at'],
        ];

        if (!isset($allowed[$table])
            || $allowed[$table]['alias'] !== $alias
            || $allowed[$table]['order'] !== $orderColumn
        ) {
            return [];
        }

        $direction = $table === 'comparison_items' ? 'ASC' : 'DESC';

        $sql = 'SELECT l.id, l.slug, l.transaction_type, l.price, l.currency,
                       l.pricing_period, p.title, p.reference_code, p.property_type,
                       p.bedrooms, p.bathrooms, p.area_value, p.area_unit,
                       c.name AS company_name, loc.name AS location_name,
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
                FROM ' . $table . ' ' . $alias . '
                INNER JOIN listings l ON l.id = ' . $alias . '.listing_id
                INNER JOIN properties p ON p.id = l.property_id
                INNER JOIN companies c ON c.id = p.company_id
                LEFT JOIN locations loc ON loc.id = p.location_id
                WHERE ' . $ownershipWhere . '
                  AND l.status = "published"
                  AND l.published_at IS NOT NULL
                  AND l.deleted_at IS NULL
                  AND (l.expires_at IS NULL OR l.expires_at > CURRENT_TIMESTAMP)
                  AND p.status = "approved"
                  AND p.deleted_at IS NULL
                  AND c.status = "approved"
                  AND c.published_at IS NOT NULL
                  AND c.deleted_at IS NULL
                ORDER BY ' . $orderColumn . ' ' . $direction . '
                LIMIT :limit';

        $statement = Database::connection()->prepare($sql);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
