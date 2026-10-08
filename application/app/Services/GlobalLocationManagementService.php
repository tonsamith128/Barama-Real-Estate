<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\GlobalLocationRepository;
use DomainException;
use InvalidArgumentException;

final class GlobalLocationManagementService
{
    private GlobalLocationRepository $locations;

    public function __construct()
    {
        $this->locations = new GlobalLocationRepository();
    }

    public function setMode(string $mode): void
    {
        $this->assertAdminPermission();
        $mode = strtolower(trim($mode));

        if (!in_array($mode, [GlobalLocationRepository::MODE_SELECTED, GlobalLocationRepository::MODE_GLOBAL], true)) {
            throw new InvalidArgumentException('Select a valid location operating mode.');
        }

        if ($mode === GlobalLocationRepository::MODE_SELECTED && $this->locations->enabledCount() < 1) {
            throw new DomainException('Enable at least one country before using Selected Countries mode.');
        }

        $this->locations->setMode($mode);
    }

    public function setCountryStatus(string $countryCode, bool $enabled): void
    {
        $this->assertAdminPermission();
        $countryCode = $this->normalizeCountryCode($countryCode);
        $country = $this->locations->country($countryCode);

        if ($country === null) {
            throw new InvalidArgumentException('Country was not found.');
        }

        if (!$enabled && (int) ($country['is_default'] ?? 0) === 1) {
            throw new DomainException('Choose another default country before disabling this country.');
        }

        if (
            !$enabled
            && $this->locations->mode() === GlobalLocationRepository::MODE_SELECTED
            && (int) ($country['enabled'] ?? 0) === 1
            && $this->locations->enabledCount() <= 1
        ) {
            throw new DomainException('Selected Countries mode must keep at least one country enabled.');
        }

        $this->locations->setCountryEnabled($countryCode, $enabled);
    }

    public function setDefaultCountry(string $countryCode): void
    {
        $this->assertAdminPermission();
        $countryCode = $this->normalizeCountryCode($countryCode);

        if ($this->locations->country($countryCode) === null) {
            throw new InvalidArgumentException('Country was not found.');
        }

        $this->locations->setDefaultCountry($countryCode);
    }

    private function normalizeCountryCode(string $countryCode): string
    {
        $countryCode = strtoupper(trim($countryCode));

        if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
            throw new InvalidArgumentException('A valid two-letter ISO country code is required.');
        }

        return $countryCode;
    }

    private function assertAdminPermission(): void
    {
        $auth = new AuthService();

        if (!$auth->check() || !$auth->hasRole('administrator') || !$auth->can('location.manage')) {
            throw new DomainException('Administrator location-management permission is required.');
        }
    }
}
