<?php

declare(strict_types=1);

namespace Barama\Services\Licensing;

use RuntimeException;

final class LicenseClientException extends RuntimeException
{
    public function __construct(public readonly string $reason, int $status = 422)
    {
        parent::__construct($reason, $status);
    }
}
