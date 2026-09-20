<?php

declare(strict_types=1);

namespace Barama\Support;

use InvalidArgumentException;

final class DomainInput
{
    /** @param array<string, mixed> $input */
    public static function requiredString(
        array $input,
        string $key,
        int $maxLength,
        int $minLength = 1
    ): string {
        $value = trim((string) ($input[$key] ?? ''));

        if (mb_strlen($value) < $minLength || mb_strlen($value) > $maxLength) {
            throw new InvalidArgumentException(
                sprintf('%s must be between %d and %d characters.', $key, $minLength, $maxLength)
            );
        }

        return $value;
    }

    /** @param array<string, mixed> $input */
    public static function optionalString(
        array $input,
        string $key,
        int $maxLength
    ): ?string {
        $value = trim((string) ($input[$key] ?? ''));

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $maxLength) {
            throw new InvalidArgumentException(
                sprintf('%s must not exceed %d characters.', $key, $maxLength)
            );
        }

        return $value;
    }

    /** @param array<string, mixed> $input */
    public static function optionalPositiveInt(array $input, string $key): ?int
    {
        $raw = $input[$key] ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        if (filter_var($raw, FILTER_VALIDATE_INT) === false || (int) $raw < 1) {
            throw new InvalidArgumentException("{$key} must be a positive integer.");
        }

        return (int) $raw;
    }

    /** @param array<string, mixed> $input */
    public static function requiredPositiveInt(array $input, string $key): int
    {
        $value = self::optionalPositiveInt($input, $key);

        if ($value === null) {
            throw new InvalidArgumentException("{$key} is required.");
        }

        return $value;
    }

    /** @param array<string, mixed> $input */
    public static function optionalDecimal(array $input, string $key): ?string
    {
        $raw = $input[$key] ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        if (!is_numeric($raw)) {
            throw new InvalidArgumentException("{$key} must be numeric.");
        }

        $number = (float) $raw;

        if (!is_finite($number) || $number < 0) {
            throw new InvalidArgumentException("{$key} must be zero or greater.");
        }

        return number_format($number, 2, '.', '');
    }

    /** @param array<string, mixed> $input */
    public static function boolean(array $input, string $key): bool
    {
        return filter_var($input[$key] ?? false, FILTER_VALIDATE_BOOL);
    }

    private function __construct()
    {
    }
}
