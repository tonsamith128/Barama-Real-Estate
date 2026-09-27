<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use PDO;

final class SeoRepository
{
    /** @return array<int,array{path:string,last_modified:?string}> */
    public function sitemapEntries(): array
    {
        $entries = [];

        $queries = [
            [
                'sql' => 'SELECT CONCAT("/properties/", l.slug) AS path,
                                 GREATEST(l.updated_at, p.updated_at) AS last_modified
                          FROM listings l
                          INNER JOIN properties p ON p.id = l.property_id
                          INNER JOIN companies c ON c.id = p.company_id
                          WHERE l.status = "published"
                            AND l.published_at IS NOT NULL
                            AND l.deleted_at IS NULL
                            AND (l.expires_at IS NULL OR l.expires_at > CURRENT_TIMESTAMP)
                            AND p.status = "approved"
                            AND p.deleted_at IS NULL
                            AND c.status = "approved"
                            AND c.published_at IS NOT NULL
                            AND c.deleted_at IS NULL',
            ],
            [
                'sql' => 'SELECT CONCAT("/projects/", p.slug) AS path,
                                 p.updated_at AS last_modified
                          FROM projects p
                          INNER JOIN companies c ON c.id = p.company_id
                          WHERE p.status = "published"
                            AND p.published_at IS NOT NULL
                            AND p.deleted_at IS NULL
                            AND c.status = "approved"
                            AND c.published_at IS NOT NULL
                            AND c.deleted_at IS NULL',
            ],
            [
                'sql' => 'SELECT CONCAT("/company/", c.slug) AS path,
                                 c.updated_at AS last_modified
                          FROM companies c
                          WHERE c.status = "approved"
                            AND c.published_at IS NOT NULL
                            AND c.deleted_at IS NULL',
            ],
        ];

        foreach ($queries as $query) {
            foreach (Database::connection()->query($query['sql'])->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $entries[] = [
                    'path' => (string) $row['path'],
                    'last_modified' => !empty($row['last_modified'])
                        ? (string) $row['last_modified']
                        : null,
                ];
            }
        }

        usort(
            $entries,
            static fn (array $left, array $right): int => strcmp($left['path'], $right['path'])
        );

        return $entries;
    }
}
