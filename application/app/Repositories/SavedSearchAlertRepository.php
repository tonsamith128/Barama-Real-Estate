<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use PDO;

final class SavedSearchAlertRepository
{
    /** @return array<int,array<string,mixed>> */
    public function dueSearches(string $frequency, int $limit = 100, bool $force = false): array
    {
        $whereFrequency = $frequency === 'all'
            ? 's.alert_frequency IN ("daily", "weekly")'
            : 's.alert_frequency = :frequency';

        $due = $force
            ? '1 = 1'
            : '(
                s.last_checked_at IS NULL
                OR (
                    s.alert_frequency = "daily"
                    AND s.last_checked_at <= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 DAY)
                )
                OR (
                    s.alert_frequency = "weekly"
                    AND s.last_checked_at <= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 7 DAY)
                )
            )';

        $sql = 'SELECT s.*, u.name AS user_name, u.email AS user_email
                FROM saved_searches s
                INNER JOIN users u ON u.id = s.user_id
                WHERE s.alerts_enabled = 1
                  AND u.status = "active"
                  AND ' . $whereFrequency . '
                  AND ' . $due . '
                ORDER BY COALESCE(s.last_checked_at, "1970-01-01 00:00:00") ASC, s.id ASC
                LIMIT :limit';

        $statement = Database::connection()->prepare($sql);

        if ($frequency !== 'all') {
            $statement->bindValue(':frequency', $frequency);
        }

        $statement->bindValue(':limit', max(1, min(500, $limit)), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed>|null */
    public function lockSearch(int $searchId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT *
             FROM saved_searches
             WHERE id = :id
             FOR UPDATE'
        );
        $statement->execute(['id' => $searchId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function recordMatch(int $searchId, int $listingId): bool
    {
        $statement = Database::connection()->prepare(
            'INSERT IGNORE INTO saved_search_matches
                (saved_search_id, listing_id, first_matched_at)
             VALUES
                (:saved_search_id, :listing_id, CURRENT_TIMESTAMP)'
        );
        $statement->execute([
            'saved_search_id' => $searchId,
            'listing_id' => $listingId,
        ]);

        return $statement->rowCount() > 0;
    }

    /** @param array<int,int> $listingIds */
    public function markNotified(int $searchId, array $listingIds): void
    {
        $listingIds = array_values(array_unique(array_filter(
            array_map('intval', $listingIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($listingIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($listingIds), '?'));
        $statement = Database::connection()->prepare(
            'UPDATE saved_search_matches
             SET last_notified_at = CURRENT_TIMESTAMP
             WHERE saved_search_id = ?
               AND listing_id IN (' . $placeholders . ')'
        );
        $statement->execute([$searchId, ...$listingIds]);
    }

    public function markChecked(int $searchId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE saved_searches
             SET last_checked_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $statement->execute(['id' => $searchId]);
    }

    public function matchCount(int $searchId): int
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*)
             FROM saved_search_matches
             WHERE saved_search_id = :saved_search_id'
        );
        $statement->execute(['saved_search_id' => $searchId]);

        return (int) $statement->fetchColumn();
    }
}
