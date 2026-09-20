<?php

declare(strict_types=1);

namespace Barama\Support;

final class Slugger
{
    public static function make(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'item';
    }

    /**
     * @param callable(string): bool $exists
     */
    public static function unique(string $value, callable $exists): string
    {
        $base = self::make($value);

        if (!$exists($base)) {
            return $base;
        }

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = $base . '-' . substr(bin2hex(random_bytes(4)), 0, 8);

            if (!$exists($candidate)) {
                return $candidate;
            }
        }

        return $base . '-' . str_replace('-', '', IdGenerator::uuid());
    }

    private function __construct()
    {
    }
}
