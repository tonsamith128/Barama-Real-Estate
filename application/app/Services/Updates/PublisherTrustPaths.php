<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;

/**
 * Staging-only root-controlled publisher trust path. Never accepts a request,
 * application config, or environment override of the authoritative location.
 *
 * Existing third-party/customer installations keep legacy path handling until
 * they receive a separately reviewed migration with a compatible installer.
 */
final class PublisherTrustPaths
{
    private const STAGING = '/www/wwwroot/baramastaging.tonsamith.com';
    private const ROOT = '/etc/barama/staging/publisher-trust';
    private const PRODUCTION = '/www/wwwroot/baramarealestate.tonsamith.com';
    private const PRODUCTION_ROOT = '/etc/barama/production/publisher-trust';
    private const KEY_NAME = 'update-publisher-v2-keys.json';
    private const FEED_NAME = 'update-feed-v2.json';

    public static function isProtectedStaging(): bool
    {
        return defined('BASE_PATH') && BASE_PATH === self::STAGING;
    }

    public static function isProtectedProduction(): bool
    {
        return defined('BASE_PATH') && BASE_PATH === self::PRODUCTION;
    }

    public static function isProtectedInstallation(): bool
    {
        return self::isProtectedStaging() || self::isProtectedProduction();
    }

    public static function directory(): string
    {
        if (self::isProtectedStaging()) return self::ROOT;
        if (self::isProtectedProduction()) return self::PRODUCTION_ROOT;
        // Preserve existing handling for unrelated/customer installations.
        return APP_PATH . '/config';
    }

    public static function keys(): string
    {
        return self::directory() . '/' . self::KEY_NAME;
    }

    public static function feed(): string
    {
        return self::directory() . '/' . self::FEED_NAME;
    }

    public static function protectedDirectoryReady(): bool
    {
        if (!self::isProtectedInstallation()) return false;
        return self::rootControlledDirectory(self::directory());
    }

    /** Check the entire fixed absolute directory chain; never follow a symlink. */
    public static function rootControlledDirectory(string $directory): bool
    {
        if ($directory === '' || $directory[0] !== '/' || $directory === '/'
            || str_contains($directory, '//') || str_ends_with($directory, '/')
            || str_contains($directory, '/./') || str_contains($directory, '/../')) {
            return false;
        }
        // Under staging's deliberately narrow open_basedir, PHP can inspect
        // the pinned leaf but cannot stat /etc or intermediate ancestors.
        // The independent root-only provisioner has already verified the full
        // directory chain. At runtime, check the exact, unsymlinked, root-owned
        // leaf and its files without exposing all of /etc to the web process.
        if (($directory === self::ROOT && self::isProtectedStaging()
            || $directory === self::PRODUCTION_ROOT && self::isProtectedProduction())
            && (string) ini_get('open_basedir') !== '') {
            clearstatcache(true, $directory);
            if (is_link($directory) || !is_dir($directory)
                || realpath($directory) !== $directory) return false;
            $leaf = @lstat($directory);
            return is_array($leaf) && $leaf['uid'] === 0
                && (($leaf['mode'] & 0022) === 0);
        }
        $cursor = '';
        foreach (explode('/', ltrim($directory, '/')) as $component) {
            if ($component === '' || $component === '.' || $component === '..') return false;
            $cursor .= '/' . $component;
            clearstatcache(true, $cursor);
            if (is_link($cursor) || !is_dir($cursor)) return false;
            $st = @lstat($cursor);
            if (!is_array($st) || $st['uid'] !== 0 || (($st['mode'] & 0022) !== 0)) {
                return false;
            }
        }
        return true;
    }

    /**
     * The staging key/feed cannot be trusted if any path component is replaceable
     * by the PHP runtime. The web process only needs read access, not write access.
     */
    public static function safeTrustFile(string $path, int $max): bool
    {
        if ($max < 1 || $max > 65536) return false;
        if (self::isProtectedInstallation()) {
            if (!in_array($path, [self::keys(), self::feed()], true)
                || !self::rootControlledDirectory(self::directory())) return false;
        }
        clearstatcache(true, $path);
        if (is_link($path) || !is_file($path) || !is_readable($path)) return false;
        $st = @lstat($path);
        if (!is_array($st) || $st['size'] < 1 || $st['size'] > $max
            || (($st['mode'] & 0022) !== 0)) return false;
        if (self::isProtectedInstallation() && $st['uid'] !== 0) return false;
        return true;
    }

    public static function assertProtectedDirectory(): void
    {
        if (!self::isProtectedInstallation() || !self::protectedDirectoryReady()) {
            throw new RuntimeException('PUBLISHER_TRUST_PROTECTED_DIRECTORY_UNAVAILABLE');
        }
    }
}
