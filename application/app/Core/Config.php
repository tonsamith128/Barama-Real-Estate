<?php

declare(strict_types=1);

namespace Barama\Core;

use RuntimeException;

final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    public static function load(string $directory): void
    {
        if (!is_dir($directory)) {
            throw new RuntimeException('Configuration directory is missing.');
        }

        $files = glob(rtrim($directory, '/') . '/*.php') ?: [];

        foreach ($files as $file) {
            $key = pathinfo($file, PATHINFO_FILENAME);
            $value = require $file;

            if (!is_array($value)) {
                throw new RuntimeException("Configuration file [{$key}] must return an array.");
            }

            self::$items[$key] = $value;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return self::$items;
    }
}
