<?php

declare(strict_types=1);

namespace Barama\Domain\RealEstate;

use InvalidArgumentException;

final class DomainRules
{
    public const LOCATION_TYPES = [
        'country',
        'province',
        'state',
        'city',
        'district',
        'commune',
        'neighborhood',
    ];

    public const COMPANY_TYPES = [
        'developer',
        'agency',
        'property_company',
        'owner',
    ];

    public const COMPANY_STATUSES = [
        'draft',
        'pending',
        'approved',
        'rejected',
        'suspended',
        'archived',
    ];

    public const PROJECT_TYPES = [
        'residential',
        'commercial',
        'mixed_use',
        'land_development',
        'industrial',
        'hospitality',
        'other',
    ];

    public const PROJECT_STATUSES = [
        'draft',
        'pending',
        'approved',
        'published',
        'rejected',
        'archived',
    ];

    public const PROJECT_COMPLETION_STATUSES = [
        'planned',
        'pre_launch',
        'under_construction',
        'completed',
        'on_hold',
    ];

    public const PROPERTY_TYPES = [
        'apartment',
        'condominium',
        'house',
        'villa',
        'townhouse',
        'land',
        'office',
        'retail',
        'warehouse',
        'industrial',
        'hotel',
        'commercial',
        'other',
    ];

    public const PROPERTY_STATUSES = [
        'draft',
        'pending',
        'approved',
        'rejected',
        'archived',
    ];

    public const LISTING_TRANSACTION_TYPES = [
        'sale',
        'rent',
    ];

    public const LISTING_STATUSES = [
        'draft',
        'pending',
        'published',
        'paused',
        'rejected',
        'closed',
        'expired',
    ];

    public const INQUIRY_STATUSES = [
        'new',
        'read',
        'replied',
        'closed',
        'spam',
    ];

    public const LEAD_STAGES = [
        'new',
        'contacted',
        'qualified',
        'viewing',
        'negotiation',
        'won',
        'lost',
    ];

    public const VIEWING_STATUSES = [
        'pending',
        'confirmed',
        'rescheduled',
        'completed',
        'declined',
        'cancelled',
    ];

    public const MEDIA_ENTITY_TYPES = [
        'company',
        'project',
        'property',
        'listing',
    ];

    private const TRANSITIONS = [
        'company' => [
            'draft' => ['pending', 'archived'],
            'pending' => ['approved', 'rejected'],
            'approved' => ['suspended', 'archived'],
            'rejected' => ['draft', 'archived'],
            'suspended' => ['approved', 'archived'],
            'archived' => ['draft'],
        ],
        'project' => [
            'draft' => ['pending', 'archived'],
            'pending' => ['approved', 'rejected'],
            'approved' => ['published', 'archived'],
            'published' => ['archived'],
            'rejected' => ['draft', 'archived'],
            'archived' => ['draft'],
        ],
        'property' => [
            'draft' => ['pending', 'archived'],
            'pending' => ['approved', 'rejected'],
            'approved' => ['archived'],
            'rejected' => ['draft', 'archived'],
            'archived' => ['draft'],
        ],
        'listing' => [
            'draft' => ['pending'],
            'pending' => ['published', 'rejected'],
            'published' => ['paused', 'closed', 'expired'],
            'paused' => ['published', 'closed', 'expired'],
            'rejected' => ['draft'],
            'closed' => [],
            'expired' => ['draft'],
        ],
        'inquiry' => [
            'new' => ['read', 'closed', 'spam'],
            'read' => ['replied', 'closed', 'spam'],
            'replied' => ['closed'],
            'closed' => [],
            'spam' => ['closed'],
        ],
        'lead' => [
            'new' => ['contacted', 'qualified', 'lost'],
            'contacted' => ['qualified', 'lost'],
            'qualified' => ['viewing', 'negotiation', 'lost'],
            'viewing' => ['qualified', 'negotiation', 'lost'],
            'negotiation' => ['won', 'lost'],
            'won' => [],
            'lost' => ['new'],
        ],
    ];

    /** @return array<int, string> */
    public static function statusesFor(string $resource): array
    {
        return match ($resource) {
            'company' => self::COMPANY_STATUSES,
            'project' => self::PROJECT_STATUSES,
            'property' => self::PROPERTY_STATUSES,
            'listing' => self::LISTING_STATUSES,
            'inquiry' => self::INQUIRY_STATUSES,
            'lead' => self::LEAD_STAGES,
            default => throw new InvalidArgumentException(
                "Unknown real-estate resource [{$resource}]."
            ),
        };
    }

    public static function assertStatus(string $resource, string $status): void
    {
        if (!in_array($status, self::statusesFor($resource), true)) {
            throw new InvalidArgumentException(
                "Invalid status [{$status}] for real-estate resource [{$resource}]."
            );
        }
    }

    public static function canTransition(
        string $resource,
        string $from,
        string $to
    ): bool {
        self::assertStatus($resource, $from);
        self::assertStatus($resource, $to);

        if ($from === $to) {
            return true;
        }

        return in_array($to, self::TRANSITIONS[$resource][$from] ?? [], true);
    }

    private function __construct()
    {
    }
}
