<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Domain\RealEstate\DomainRules;
use Barama\Repositories\CatalogRepository;
use Barama\Support\DomainInput;
use Barama\Support\Slugger;
use DomainException;
use InvalidArgumentException;

final class CatalogManagementService
{
    private CatalogRepository $catalog;

    public function __construct()
    {
        $this->catalog = new CatalogRepository();
    }

    /** @param array<string, mixed> $input */
    public function createLocation(array $input): int
    {
        $this->assertAdminPermission('location.manage');

        $name = DomainInput::requiredString($input, 'name', 160, 2);
        $type = DomainInput::requiredString($input, 'type', 32);

        if (!in_array($type, DomainRules::LOCATION_TYPES, true)) {
            throw new InvalidArgumentException('Invalid location type.');
        }

        $parentId = DomainInput::optionalPositiveInt($input, 'parent_id');

        if ($parentId !== null && !$this->catalog->locationExists($parentId)) {
            throw new InvalidArgumentException('Parent location does not exist.');
        }

        $countryCode = strtoupper((string) ($input['country_code'] ?? ''));
        $countryCode = $countryCode !== '' ? $countryCode : null;

        if ($countryCode !== null && preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
            throw new InvalidArgumentException('country_code must be a two-letter ISO code.');
        }

        $latitude = DomainInput::optionalSignedDecimal($input, 'latitude');
        $longitude = DomainInput::optionalSignedDecimal($input, 'longitude');

        if ($latitude !== null && ((float) $latitude < -90 || (float) $latitude > 90)) {
            throw new InvalidArgumentException('Latitude must be between -90 and 90.');
        }

        if ($longitude !== null && ((float) $longitude < -180 || (float) $longitude > 180)) {
            throw new InvalidArgumentException('Longitude must be between -180 and 180.');
        }

        $slug = Slugger::unique(
            (string) ($input['slug'] ?? $name),
            fn (string $candidate): bool => $this->catalog->locationSlugExists($candidate)
        );

        return $this->catalog->createLocation([
            'parent_id' => $parentId,
            'type' => $type,
            'country_code' => $countryCode,
            'name' => $name,
            'slug' => $slug,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'sort_order' => (int) ($input['sort_order'] ?? 0),
        ]);
    }

    /** @param array<string, mixed> $input */
    public function createAmenity(array $input): int
    {
        $this->assertAdminPermission('amenity.manage');

        $name = DomainInput::requiredString($input, 'name', 140, 2);
        $slug = Slugger::unique(
            (string) ($input['slug'] ?? $name),
            fn (string $candidate): bool => $this->catalog->amenitySlugExists($candidate)
        );

        return $this->catalog->createAmenity([
            'name' => $name,
            'slug' => $slug,
            'category' => DomainInput::optionalString($input, 'category', 80) ?? 'general',
            'icon_key' => DomainInput::optionalString($input, 'icon_key', 100),
            'sort_order' => (int) ($input['sort_order'] ?? 0),
        ]);
    }

    public function setLocationStatus(int $id, string $status): void
    {
        $this->assertAdminPermission('location.manage');
        $status = strtolower(trim($status));

        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException('Invalid location status.');
        }

        $location = $this->catalog->location($id);

        if ($location === null) {
            throw new InvalidArgumentException('Location was not found.');
        }

        if ((string) $location['status'] === $status) {
            return;
        }

        if ($status === 'active') {
            $parentId = isset($location['parent_id']) ? (int) $location['parent_id'] : 0;

            if ($parentId > 0) {
                $parent = $this->catalog->location($parentId);

                if ($parent === null || (string) $parent['status'] !== 'active') {
                    throw new DomainException('Reactivate the parent location before activating this location.');
                }
            }
        } else {
            $usage = $this->catalog->locationUsage($id);
            $directUsage = $usage['companies']
                + $usage['projects']
                + $usage['properties']
                + $usage['leads']
                + $usage['saved_searches'];

            if ($usage['active_children'] > 0) {
                throw new DomainException('Deactivate child locations before deactivating this parent location.');
            }

            if ($directUsage > 0) {
                throw new DomainException('This location is still used by real-estate inventory, leads, or saved property searches and cannot be deactivated yet.');
            }
        }

        $this->catalog->setLocationStatus($id, $status);
    }

    public function setAmenityStatus(int $id, string $status): void
    {
        $this->assertAdminPermission('amenity.manage');
        $status = strtolower(trim($status));

        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new InvalidArgumentException('Invalid amenity status.');
        }

        $amenity = $this->catalog->amenity($id);

        if ($amenity === null) {
            throw new InvalidArgumentException('Amenity was not found.');
        }

        if ((string) $amenity['status'] === $status) {
            return;
        }

        if ($status === 'inactive') {
            $usage = $this->catalog->amenityUsage($id);

            if (($usage['projects'] + $usage['properties']) > 0) {
                throw new DomainException('This amenity is still assigned to active real-estate inventory and cannot be deactivated yet.');
            }
        }

        $this->catalog->setAmenityStatus($id, $status);
    }

    private function assertAdminPermission(string $permission): void
    {
        $auth = new AuthService();

        if (!$auth->check() || !$auth->hasRole('administrator') || !$auth->can($permission)) {
            throw new DomainException('Administrator permission is required.');
        }
    }
}
