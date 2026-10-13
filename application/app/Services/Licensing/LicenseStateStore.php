<?php

declare(strict_types=1);

namespace Barama\Services\Licensing;

/**
 * The existing host must implement this using its encrypted private-settings
 * service, never a new entitlement/license database and never browser storage.
 */
interface LicenseStateStore
{
    /** @return array{token:string,domain:string,installation_id:string,product:string}|null */
    public function load(): ?array;

    /** @param array{token:string,domain:string,installation_id:string,product:string} $state */
    public function save(array $state): void;

    public function clear(): void;
}
