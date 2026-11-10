<?php

declare(strict_types=1);

namespace App\Services\Geography;

use App\Models\Location;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * Read-only location hierarchy for public discovery and future admin pickers.
 * Never infer a parent from free-text addresses or migrate legacy IDs directly.
 */
final class LocationHierarchy
{
    private const MAX_DEPTH = 16;
    private const MAX_RESULT_IDS = 10000;

    /**
     * @return array<int, Location> Ordered from country/root to selected location.
     */
    public function activePath(int $locationId): array
    {
        if ($locationId < 1) {
            return [];
        }

        $path = [];
        $seen = [];
        $current = Location::query()->find($locationId);
        $country = $current?->country_code;

        while ($current !== null && count($path) < self::MAX_DEPTH) {
            $key = (int) $current->getKey();
            if (isset($seen[$key]) || $current->status !== 'active'
                || $current->country_code !== $country) {
                return [];
            }

            $seen[$key] = true;
            array_unshift($path, $current);

            if ($current->parent_id === null) {
                return $path;
            }

            $current = Location::query()->find((int) $current->parent_id);
        }

        // Orphaned or over-deep paths must not be used as public filters.
        return [];
    }

    /**
     * @return Collection<int, Location>
     */
    public function activeChildren(string $countryCode, ?int $parentId = null): Collection
    {
        $countryCode = strtoupper(trim($countryCode));

        if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
            return new Collection();
        }

        if ($parentId !== null) {
            $path = $this->activePath($parentId);
            if ($path === [] || end($path)->country_code !== $countryCode) {
                return new Collection();
            }
        }

        $query = Location::query()->active()->where('country_code', $countryCode);
        $parentId === null
            ? $query->whereNull('parent_id')
            : $query->where('parent_id', $parentId);

        return $query->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->limit(1000)->get();
    }

    /**
     * Return the selected location and its active descendants. This works on
     * MySQL 8 and SQLite without a vendor-specific recursive SQL statement.
     *
     * @return array<int, int>
     */
    public function activeDescendantIds(int $locationId): array
    {
        $path = $this->activePath($locationId);
        if ($path === []) {
            return [];
        }

        $root = end($path);
        $countryCode = $root->country_code;
        $seen = [(int) $root->getKey() => true];
        $frontier = [(int) $root->getKey()];

        for ($depth = 0; $depth < self::MAX_DEPTH && $frontier !== []; $depth++) {
            $query = Location::query()->active()->whereIn('parent_id', $frontier);
            $countryCode === null
                ? $query->whereNull('country_code')
                : $query->where('country_code', $countryCode);

            $next = [];
            foreach ($query->pluck('id') as $id) {
                $id = (int) $id;
                if (!isset($seen[$id])) {
                    $seen[$id] = true;
                    $next[] = $id;
                }
            }

            if (count($seen) > self::MAX_RESULT_IDS) {
                throw new RuntimeException('Location hierarchy exceeds the safe query limit.');
            }

            $frontier = $next;
        }

        if ($frontier !== []) {
            throw new RuntimeException('Location hierarchy exceeds the supported depth.');
        }

        return array_keys($seen);
    }
}
