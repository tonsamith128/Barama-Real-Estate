<?php

declare(strict_types=1);

namespace Barama\Services\Licensing;

/** Allows the dashboard to render saved status without any outbound request. */
final class OfflineLicenseTransport implements LicenseTransport
{
    public function post(string $action, array $payload): array
    {
        throw new LicenseClientException('PUBLISHER_ORIGIN_NOT_CONFIGURED', 503);
    }
}
