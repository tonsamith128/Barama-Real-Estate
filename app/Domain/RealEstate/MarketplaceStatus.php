<?php

declare(strict_types=1);

namespace App\Domain\RealEstate;

/**
 * Stable database states shared by the marketplace query layer.
 *
 * State-transition permissions will be implemented in separate write actions.
 */
final class MarketplaceStatus
{
    public const COMPANY_APPROVED = 'approved';
    public const PROPERTY_APPROVED = 'approved';
    public const LISTING_PUBLISHED = 'published';

    public const PROPERTY_TYPES = [
        'apartment', 'condominium', 'house', 'villa', 'townhouse', 'land',
        'office', 'retail', 'warehouse', 'industrial', 'hotel', 'commercial', 'other',
    ];

    private function __construct()
    {
    }
}
