<?php

declare(strict_types=1);

namespace Barama\Support;

final class IdGenerator
{
    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    public static function reference(string $prefix = 'BRM'): string
    {
        return strtoupper($prefix) . '-' . strtoupper(bin2hex(random_bytes(5)));
    }

    private function __construct()
    {
    }
}
