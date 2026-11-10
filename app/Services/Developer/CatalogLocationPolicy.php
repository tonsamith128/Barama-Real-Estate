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

        $path = $this->hierarchy->activePath($locationId);
        if ($path === []) {
            throw ValidationException::withMessages([
                'location_id' => 'Choose an active location with a valid geographic hierarchy.',
            ]);
        }

        // Fresh installs without imported country governance retain existing
        // location behavior. Once configured, disabled/unregistered countries
        // cannot be selected for new catalog writes.
        if (\App\Models\LocationCountry::query()->exists()) {
            $country = strtoupper((string) end($path)->country_code);
            if ($country === '' || !\App\Models\LocationCountry::query()
                ->where('country_code', $country)->where('enabled', true)->exists()) {
                throw ValidationException::withMessages([
                    'location_id' => 'Selected location country is not enabled.',
                ]);
            }
        }
    }
}
