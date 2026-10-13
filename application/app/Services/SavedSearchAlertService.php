<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\MarketplaceRepository;
use Barama\Repositories\SavedSearchAlertRepository;
use InvalidArgumentException;
use Throwable;

final class SavedSearchAlertService
{
    /** @return array{processed:int,matched:int,notified:int} */
    public function process(
        string $frequency = 'all',
        int $limit = 100,
        bool $force = false
    ): array {
        if (!in_array($frequency, ['all', 'daily', 'weekly'], true)) {
            throw new InvalidArgumentException('Alert frequency must be daily, weekly, or all.');
        }

        $repository = new SavedSearchAlertRepository();
        $result = ['processed' => 0, 'matched' => 0, 'notified' => 0];

        foreach ($repository->dueSearches($frequency, $limit, $force) as $candidate) {
            $processed = $this->processSearch((int) $candidate['id']);

            $result['processed']++;
            $result['matched'] += $processed['matched'];
            $result['notified'] += $processed['notified'];
        }

        return $result;
    }

    /** @return array{matched:int,notified:int} */
    private function processSearch(int $searchId): array
    {
        $pdo = Database::connection();
        $repository = new SavedSearchAlertRepository();

        try {
            $pdo->beginTransaction();
            $search = $repository->lockSearch($searchId);

            if ($search === null || !(bool) $search['alerts_enabled']) {
                $pdo->commit();
                return ['matched' => 0, 'notified' => 0];
            }

            $filters = [
                'q' => (string) ($search['query_text'] ?? ''),
                'offer' => (string) ($search['transaction_type'] ?? ''),
                'type' => (string) ($search['property_type'] ?? ''),
                'location' => $search['location_id'],
                'bedrooms' => $search['bedrooms_min'],
                'min_price' => $search['price_min'],
                'max_price' => $search['price_max'],
                'currency' => (string) ($search['currency'] ?? ''),
                'sort' => 'newest',
            ];

            $matches = (new MarketplaceRepository())->listings($filters, 1, 50);
            $newMatches = [];

            foreach ($matches['rows'] as $listing) {
                if ($repository->recordMatch($searchId, (int) $listing['id'])) {
                    $newMatches[] = $listing;
                }
            }

            if ($newMatches !== []) {
                $this->queueDigest($search, $newMatches);
                $repository->markNotified(
                    $searchId,
                    array_map(
                        static fn (array $listing): int => (int) $listing['id'],
                        $newMatches
                    )
                );
            }

            $repository->markChecked($searchId);
            $pdo->commit();

            return [
                'matched' => count($newMatches),
                'notified' => $newMatches !== [] ? 1 : 0,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /** @param array<string,mixed> $search @param array<int,array<string,mixed>> $matches */
    private function queueDigest(array $search, array $matches): void
    {
        $ids = array_map(
            static fn (array $listing): int => (int) $listing['id'],
            $matches
        );
        sort($ids);

        $count = count($matches);
        $name = (string) $search['name'];
        $actionUrl = $this->searchUrl($search);
        $dedupe = 'saved-search:' . (int) $search['id']
            . ':matches:' . hash('sha256', implode(',', $ids));

        $itemsHtml = '';
        $itemsText = [];

        foreach (array_slice($matches, 0, 10) as $listing) {
            $price = strtoupper((string) $listing['currency'])
                . ' ' . number_format((float) $listing['price'], 0);
            $listingUrl = url('/properties/' . rawurlencode((string) $listing['slug']));
            $itemsHtml .= '<li><a href="' . e($listingUrl) . '">'
                . e((string) $listing['title']) . '</a> — ' . e($price) . '</li>';
            $itemsText[] = (string) $listing['title'] . ' — ' . $price
                . ' — ' . $listingUrl;
        }

        $title = $count . ' new '
            . ($count === 1 ? 'property matches' : 'properties match')
            . ' “' . $name . '”';
        $message = 'New published inventory matches your saved property search.';

        (new NotificationService())->notifyUser(
            (int) $search['user_id'],
            'saved_search_alerts',
            [
                'type' => 'saved_search_matches',
                'title' => $title,
                'message' => $message,
                'action_url' => $actionUrl,
                'entity_type' => 'saved_search',
                'entity_id' => (int) $search['id'],
                'dedupe_key' => $dedupe,
                'email_variables' => [
                    'search_name' => $name,
                    'match_count' => (string) $count,
                    'matches_summary' => implode("\n", $itemsText),
                ],
                'email_subject' => $title . ' · Barama Real Estate',
                'email_html' => '<p><strong>' . e($title) . '</strong></p>'
                    . '<p>' . e($message) . '</p><ul>' . $itemsHtml . '</ul>'
                    . '<p><a href="' . e(url($actionUrl)) . '">View all current matches</a></p>',
                'email_text' => $title . "\n\n" . $message . "\n\n"
                    . implode("\n", $itemsText)
                    . "\n\nView all current matches: " . url($actionUrl),
            ]
        );
    }

    /** @param array<string,mixed> $search */
    private function searchUrl(array $search): string
    {
        $query = array_filter([
            'q' => $search['query_text'] ?? '',
            'offer' => $search['transaction_type'] ?? '',
            'type' => $search['property_type'] ?? '',
            'location' => $search['location_id'] ?? '',
            'bedrooms' => $search['bedrooms_min'] ?? '',
            'min_price' => $search['price_min'] ?? '',
            'max_price' => $search['price_max'] ?? '',
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        return '/properties' . ($query !== [] ? '?' . http_build_query($query) : '');
    }
}
