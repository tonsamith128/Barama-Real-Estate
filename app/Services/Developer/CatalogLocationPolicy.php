<?php

declare(strict_types=1);

namespace App\Services\Developer;

use App\Models\Location;
use Illuminate\Validation\ValidationException;

/**
 * Match legacy parent-based geography without introducing another location
 * table. Inactive parents, circular ancestry and cross-country links fail.
 */
final class CatalogLocationPolicy
{
    public function assertSelectable(?int $locationId): void
    {
        if ($locationId === null) {
            return;
        }

        $current = Location::query()->find($locationId);
        $visited = [];
        $country = null;

        for ($depth = 0; $depth < 24; $depth++) {
            if (!$current || $current->status !== 'active'
                || isset($visited[$current->getKey()])) {
                $this->invalid();
            }

            $visited[$current->getKey()] = true;
            $code = strtoupper(trim((string) $current->country_code));
            if ($code !== '') {
                if ($country !== null && $country !== $code) {
                    $this->invalid();
                }
                $country = $code;
            }

            if ($current->parent_id === null) {
                return;
            }

            $current = Location::query()->find($current->parent_id);
        }

        $this->invalid();
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages([
            'location_id' => 'Choose an active location with a valid geographic hierarchy.',
        ]);
    }
}
