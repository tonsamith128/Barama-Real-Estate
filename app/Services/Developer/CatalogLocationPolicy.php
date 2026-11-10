<?php

declare(strict_types=1);

namespace App\Services\Developer;

use App\Services\Geography\LocationHierarchy;
use Illuminate\Validation\ValidationException;

/**
 * Shared catalog boundary for PR #184's canonical location hierarchy.
 * Never accept inactive, cross-country, cyclic or orphaned selections.
 */
final class CatalogLocationPolicy
{
    public function __construct(private readonly LocationHierarchy $hierarchy)
    {
    }

    public function assertSelectable(?int $locationId): void
    {
        if ($locationId === null) {
            return;
        }

        if ($this->hierarchy->activePath($locationId) === []) {
            throw ValidationException::withMessages([
                'location_id' => 'Choose an active location with a valid geographic hierarchy.',
            ]);
        }
    }
}
