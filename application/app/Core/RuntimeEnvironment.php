<?php

declare(strict_types=1);

namespace Barama\Core;

use RuntimeException;

final class RuntimeEnvironment
{
    /** @var array<string,mixed> */
    private static array $values = [];

    public static function load(?string $path = null): void
    {
        $path ??= APP_PATH . '/storage/app/installation/environment.php';

        if (!is_file($path)) {
            self::$values = [];
            return;
        }

        $values = require $path;

        if (!is_array($values)) {
            throw new RuntimeException('Runtime configuration is invalid.');
        }

        self::$values = $values;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$values);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$values[$key] ?? $default;
    }

    /** @return array<string,mixed> */
    public static function all(): array
    {
        return self::$values;
    }

    private function __construct()
    {
    }
}
