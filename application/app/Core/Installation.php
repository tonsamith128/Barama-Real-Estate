<?php

declare(strict_types=1);

namespace Barama\Core;

use RuntimeException;

final class Installation
{
    public static function isInstalled(): bool
    {
        return is_file(self::lockPath());
    }

    public static function lockPath(): string
    {
        return APP_PATH . '/storage/app/installation/installed.php';
    }

    public static function runtimeConfigPath(): string
    {
        return APP_PATH . '/storage/app/installation/environment.php';
    }

    public static function installationIdPath(): string
    {
        return APP_PATH . '/storage/app/license/installation-id.php';
    }

    public static function licensePath(): string
    {
        return APP_PATH . '/storage/app/license/license.php';
    }

    /** @return array<int,string> */
    public static function ensureRuntimeDirectories(): array
    {
        $directories = [
            APP_PATH . '/storage',
            APP_PATH . '/storage/cache',
            APP_PATH . '/storage/logs',
            APP_PATH . '/storage/uploads',
            APP_PATH . '/storage/uploads/private',
            APP_PATH . '/storage/app',
            APP_PATH . '/storage/app/installation',
            APP_PATH . '/storage/app/license',
            APP_PATH . '/storage/framework',
            APP_PATH . '/storage/framework/cache',
        ];

        foreach ($directories as $directory) {
            if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('A required runtime directory could not be created.');
            }
        }

        return $directories;
    }

    public static function installationId(): string
    {
        self::ensureRuntimeDirectories();
        $state = self::readState(self::installationIdPath());
        $existing = trim((string) ($state['installation_id'] ?? ''));

        if (preg_match('/^[a-f0-9]{32}$/', $existing) === 1) {
            return $existing;
        }

        $installationId = bin2hex(random_bytes(16));
        self::writeState(self::installationIdPath(), [
            'installation_id' => $installationId,
            'created_at' => gmdate('c'),
        ]);

        return $installationId;
    }

    /** @return array<string,mixed> */
    public static function metadata(): array
    {
        return self::readState(self::lockPath());
    }

    /** @return array<string,mixed> */
    public static function runtimeConfig(): array
    {
        return self::readState(self::runtimeConfigPath());
    }

    /** @return array<string,mixed> */
    public static function licenseState(): array
    {
        return self::readState(self::licensePath());
    }

    /** @param array<string,mixed> $values */
    public static function writeRuntimeConfig(array $values): void
    {
        if (self::isInstalled()) {
            throw new RuntimeException('Runtime configuration cannot be replaced after installation.');
        }

        self::writeState(self::runtimeConfigPath(), $values);
    }

    /** @param array<string,mixed> $values */
    public static function writeLicenseState(array $values): void
    {
        if (self::isInstalled()) {
            throw new RuntimeException('License state cannot be replaced after installation.');
        }

        unset($values['purchase_code'], $values['license_key']);
        self::writeState(self::licensePath(), $values);
    }

    /** @param array<string,mixed> $values */
    public static function refreshLicenseState(array $values): void
    {
        if (!self::isInstalled()) {
            throw new RuntimeException('License state can be refreshed only after installation.');
        }

        unset($values['purchase_code'], $values['license_key']);

        self::writeState(
            self::licensePath(),
            array_merge(self::licenseState(), $values)
        );
    }

    /** @param array<string,mixed> $values */
    public static function updateInstalledMetadata(array $values): void
    {
        if (!self::isInstalled()) {
            throw new RuntimeException('Installation metadata is unavailable.');
        }

        unset($values['installed_at'], $values['installation_id']);

        self::writeState(
            self::lockPath(),
            array_merge(self::metadata(), $values)
        );
    }

    /** @param array<string,mixed> $metadata */
    public static function markInstalled(array $metadata): void
    {
        if (self::isInstalled()) {
            throw new RuntimeException('Barama Real Estate is already installed.');
        }

        self::writeState(self::lockPath(), array_merge([
            'installed_at' => gmdate('c'),
            'version' => defined('APP_VERSION') ? APP_VERSION : null,
        ], $metadata));
    }

    public static function removeIncompleteState(): void
    {
        foreach ([self::lockPath(), self::runtimeConfigPath(), self::licensePath()] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /** @return array<string,mixed> */
    private static function readState(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $value = require $path;

        return is_array($value) ? $value : [];
    }

    /** @param array<string,mixed> $values */
    private static function writeState(string $path, array $values): void
    {
        $directory = dirname($path);

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Runtime state directory could not be created.');
        }

        $payload = "<?php\n\ndeclare(strict_types=1);\n\n"
            . "if (!defined('BARAMA_BOOTSTRAP')) {\n"
            . "    http_response_code(404);\n"
            . "    exit;\n"
            . "}\n\nreturn "
            . var_export($values, true)
            . ";\n";

        $temporary = $path . '.installing-' . bin2hex(random_bytes(6));

        if (@file_put_contents($temporary, $payload, LOCK_EX) === false) {
            throw new RuntimeException('Runtime state could not be written.');
        }

        @chmod($temporary, 0640);

        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Runtime state could not be finalized.');
        }

        @chmod($path, 0640);
    }

    private function __construct()
    {
    }
}
