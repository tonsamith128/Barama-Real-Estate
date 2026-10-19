<?php

declare(strict_types=1);

namespace Barama\Services;

use DomainException;

final class UserDisplayIdService
{
    public static function format(int $internalId): string
    {
        if ($internalId < 1 || $internalId >= 100000000) {
            throw new DomainException('The user ID is outside the supported eight-digit public UID range.');
        }
        // A reversible full-cycle permutation, distinct from the database primary key.
        return sprintf('%08d', (($internalId * 7304399) + 18472961) % 100000000);
    }
}
