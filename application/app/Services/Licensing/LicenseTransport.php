<?php

declare(strict_types=1);

namespace Barama\Services\Licensing;

/** Transport implementation lives exclusively in the customer installation. */
interface LicenseTransport
{
    /** @return array{status:int,body:array<string,mixed>} */
    public function post(string $action, array $payload): array;
}
