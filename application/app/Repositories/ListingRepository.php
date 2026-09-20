<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use PDO;

final class ListingRepository
{
    /** @return array<string, mixed>|null */
    public function findPublicBySlug(string $slug): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT listings.*,
                    properties.title AS property_title,
                    properties.slug AS property_slug,
                    properties.reference_code,
                    properties.property_type,
                    properties.company_id,
                    properties.project_id,
                    properties.location_id
             FROM listings
             INNER JOIN properties ON properties.id = listings.property_id
             LEFT JOIN companies ON companies.id = properties.company_id
             WHERE listings.slug = :slug
               AND listings.status = :listing_status
               AND listings.published_at IS NOT NULL
               AND listings.deleted_at IS NULL
               AND (listings.expires_at IS NULL OR listings.expires_at > CURRENT_TIMESTAMP)
               AND properties.status = :property_status
               AND properties.deleted_at IS NULL
               AND (
                    properties.company_id IS NULL
                    OR (
                        companies.status = :company_status
                        AND companies.published_at IS NOT NULL
                        AND companies.deleted_at IS NULL
                    )
               )
             LIMIT 1'
        );
        $statement->execute([
            'slug' => trim($slug),
            'listing_status' => 'published',
            'property_status' => 'approved',
            'company_status' => 'approved',
        ]);

        $listing = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($listing) ? $listing : null;
    }
}
