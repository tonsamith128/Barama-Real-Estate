<?php

declare(strict_types=1);

namespace Barama\Services;

/**
 * A published company may receive a media correction from an authorized
 * platform administrator without temporarily unpublishing its profile.
 *
 * This is deliberately not an override for developers or for published
 * projects, properties, and listings.
 */
final class ApprovedCompanyMediaEditPolicy
{
    public static function allows(
        string $entityType,
        string $status,
        bool $isAdministrator,
        bool $hasDashboardAccess,
        bool $canManageCompanies,
        bool $canManageMedia
    ): bool {
        return $entityType === 'company'
            && $status === 'approved'
            && $isAdministrator
            && $hasDashboardAccess
            && $canManageCompanies
            && $canManageMedia;
    }
}
