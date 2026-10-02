<?php

declare(strict_types=1);

namespace Barama\Services;

use InvalidArgumentException;
use RuntimeException;

final class AdminSettingsService
{
    public const SITE_MEDIA_LIMITS_MB = [5, 10, 25, 50];
    public const TEMP_RETENTION_HOURS = [1, 6, 12, 24, 72];

    /** @return array{site_media_upload_limit_mb:int,temp_retention_hours:int} */
    public function storageConfig(): array
    {
        $defaults = [
            'site_media_upload_limit_mb' => 50,
            'temp_retention_hours' => 24,
        ];

        $path = $this->configPath();

        if (!is_file($path)) {
            return $defaults;
        }

        $json = @file_get_contents($path);
        $decoded = is_string($json) ? json_decode($json, true) : null;

        if (!is_array($decoded)) {
            return $defaults;
        }

        $limit = (int) ($decoded['site_media_upload_limit_mb'] ?? 50);
        if (!in_array($limit, self::SITE_MEDIA_LIMITS_MB, true)) {
            $limit = 50;
        }

        $retention = (int) ($decoded['temp_retention_hours'] ?? 24);
        if (!in_array($retention, self::TEMP_RETENTION_HOURS, true)) {
            $retention = 24;
        }

        return [
            'site_media_upload_limit_mb' => $limit,
            'temp_retention_hours' => $retention,
        ];
    }

    /** @param array<string,mixed> $input
     *  @return array{site_media_upload_limit_mb:int,temp_retention_hours:int}
     */
    public function saveStorageConfig(array $input): array
    {
        $limit = (int) ($input['site_media_upload_limit_mb'] ?? 50);
        if (!in_array($limit, self::SITE_MEDIA_LIMITS_MB, true)) {
            throw new InvalidArgumentException(
                'Select a supported site-media upload limit.'
            );
        }

        $retention = (int) ($input['temp_retention_hours'] ?? 24);
        if (!in_array($retention, self::TEMP_RETENTION_HOURS, true)) {
            throw new InvalidArgumentException(
                'Select a supported temporary-file retention period.'
            );
        }

        $config = [
            'site_media_upload_limit_mb' => $limit,
            'temp_retention_hours' => $retention,
        ];

        $directory = dirname($this->configPath());
        $this->ensureDirectory($directory);

        $json = json_encode(
            $config,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        if (!is_string($json)) {
            throw new RuntimeException(
                'Storage configuration could not be encoded.'
            );
        }

        $path = $this->configPath();
        $tmp = $path . '.tmp-' . bin2hex(random_bytes(6));

        if (@file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException(
                'Storage configuration could not be written.'
            );
        }

        @chmod($tmp, 0640);

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException(
                'Storage configuration could not be activated.'
            );
        }

        return $config;
    }

    /** @return array<string,mixed> */
    public function storageDiagnostics(): array
    {
        $root = APP_PATH . '/storage';
        $uploads = $root . '/uploads/private';
        $temp = $root . '/tmp';
        $settings = dirname($this->configPath());

        $this->ensureDirectory($root);
        $this->ensureDirectory($uploads);
        $this->ensureDirectory($temp);
        $this->ensureDirectory($settings);

        $config = $this->storageConfig();

        $free = @disk_free_space($root);
        $total = @disk_total_space($root);
        $freeBytes = is_float($free) || is_int($free) ? (int) $free : 0;
        $totalBytes = is_float($total) || is_int($total) ? (int) $total : 0;
        $usedBytes = $totalBytes > 0
            ? max(0, $totalBytes - $freeBytes)
            : 0;
        $usedPercent = $totalBytes > 0
            ? round(($usedBytes / $totalBytes) * 100, 1)
            : 0.0;

        $tempStats = $this->directoryStats($temp);
        $configuredLimit = $config['site_media_upload_limit_mb'];
        $retention = $config['temp_retention_hours'];

        $systemLimitBytes = max(
            1,
            (int) config(
                'domain.site_media_max_upload_bytes',
                50 * 1024 * 1024
            )
        );

        $effectiveLimitBytes = min(
            $systemLimitBytes,
            $configuredLimit * 1024 * 1024
        );

        return [
            'storage_root' => $root,
            'uploads_root' => $uploads,
            'temp_root' => $temp,
            'config_path' => $this->configPath(),
            'storage_writable' => is_writable($root),
            'uploads_writable' => is_writable($uploads),
            'temp_writable' => is_writable($temp),
            'config_writable' => is_writable($settings),
            'free_bytes' => $freeBytes,
            'total_bytes' => $totalBytes,
            'used_bytes' => $usedBytes,
            'used_percent' => $usedPercent,
            'temp_files' => $tempStats['files'],
            'temp_bytes' => $tempStats['bytes'],
            'site_media_upload_limit_mb' => $configuredLimit,
            'effective_site_media_limit_bytes' => $effectiveLimitBytes,
            'temp_retention_hours' => $retention,
            'php_upload_max' => (string) ini_get('upload_max_filesize'),
            'php_post_max' => (string) ini_get('post_max_size'),
        ];
    }

    public function testWrite(): void
    {
        $temp = APP_PATH . '/storage/tmp';
        $this->ensureDirectory($temp);

        if (!is_writable($temp)) {
            throw new RuntimeException(
                'Temporary storage is not writable by the application.'
            );
        }

        $path = $temp . '/.barama-storage-test-' . bin2hex(random_bytes(8));
        $payload = 'barama-storage-test:' . bin2hex(random_bytes(16));

        if (@file_put_contents($path, $payload, LOCK_EX) === false) {
            throw new RuntimeException(
                'Storage write test could not create a temporary file.'
            );
        }

        $readBack = @file_get_contents($path);
        @unlink($path);

        if ($readBack !== $payload) {
            throw new RuntimeException(
                'Storage write test could not verify written bytes.'
            );
        }
    }

    /** @return array{files:int,bytes:int} */
    public function cleanupTemp(int $retentionHours): array
    {
        if (!in_array(
            $retentionHours,
            self::TEMP_RETENTION_HOURS,
            true
        )) {
            throw new InvalidArgumentException(
                'Temporary-file retention is invalid.'
            );
        }

        $temp = APP_PATH . '/storage/tmp';
        $this->ensureDirectory($temp);

        $realTemp = realpath($temp);

        if ($realTemp === false || !is_dir($realTemp)) {
            throw new RuntimeException(
                'Temporary storage could not be resolved.'
            );
        }

        $cutoff = time() - ($retentionHours * 3600);
        $deletedFiles = 0;
        $deletedBytes = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $realTemp,
                \FilesystemIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();

            if ($item->isLink()) {
                continue;
            }

            $resolved = realpath($path);

            if (
                $resolved === false
                || !str_starts_with(
                    $resolved,
                    $realTemp . DIRECTORY_SEPARATOR
                )
            ) {
                continue;
            }

            if ($item->isDir()) {
                @rmdir($resolved);
                continue;
            }

            if (!$item->isFile() || $item->getMTime() > $cutoff) {
                continue;
            }

            $size = max(0, (int) $item->getSize());

            if (@unlink($resolved)) {
                $deletedFiles++;
                $deletedBytes += $size;
            }
        }

        return [
            'files' => $deletedFiles,
            'bytes' => $deletedBytes,
        ];
    }

    private function configPath(): string
    {
        return APP_PATH . '/storage/settings/admin-storage.json';
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (
            !@mkdir($directory, 0775, true)
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'Required storage directory could not be created.'
            );
        }
    }

    /** @return array{files:int,bytes:int} */
    private function directoryStats(string $directory): array
    {
        if (!is_dir($directory)) {
            return ['files' => 0, 'bytes' => 0];
        }

        $files = 0;
        $bytes = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $directory,
                \FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $item) {
            if ($item->isFile() && !$item->isLink()) {
                $files++;
                $bytes += max(0, (int) $item->getSize());
            }
        }

        return ['files' => $files, 'bytes' => $bytes];
    }
}
