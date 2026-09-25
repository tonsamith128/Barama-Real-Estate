<?php

declare(strict_types=1);

namespace Barama\Support;

final class MigrationCatalog
{
    /**
     * Historical migration filenames that must be treated as equivalent to
     * the current canonical filename during upgrades.
     *
     * @var array<string,array<int,string>>
     */
    private const LEGACY_ALIASES = [
        '0011_create_workspace_preferences.php' => [
            '0010_create_workspace_preferences.php',
        ],
    ];

    /** @return array<int,string> */
    public static function files(): array
    {
        $files = glob(APP_PATH . '/database/migrations/*.php') ?: [];
        sort($files, SORT_STRING);

        return $files;
    }

    /** @param array<int,mixed> $applied @return array<string,bool> */
    public static function appliedLookup(array $applied): array
    {
        $lookup = array_fill_keys(
            array_map(
                static fn (mixed $name): string => (string) $name,
                $applied
            ),
            true
        );

        foreach (self::LEGACY_ALIASES as $canonical => $legacyNames) {
            if (isset($lookup[$canonical])) {
                continue;
            }

            foreach ($legacyNames as $legacy) {
                if (isset($lookup[$legacy])) {
                    $lookup[$canonical] = true;
                    break;
                }
            }
        }

        return $lookup;
    }

    /** @param array<int,mixed> $applied @return array<int,string> */
    public static function pending(array $applied): array
    {
        $lookup = self::appliedLookup($applied);

        return array_values(array_filter(
            array_map('basename', self::files()),
            static fn (string $name): bool => !isset($lookup[$name])
        ));
    }

    /** @return array<string,array<int,string>> */
    public static function legacyAliases(): array
    {
        return self::LEGACY_ALIASES;
    }

    private function __construct()
    {
    }
}
