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

    private function assertAdminPermission(string $permission): void
    {
        $auth = new AuthService();

        if (!$auth->check() || !$auth->hasRole('administrator') || !$auth->can($permission)) {
            throw new DomainException('Administrator permission is required.');
        }
    }
}
