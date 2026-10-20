<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;

/**
 * Staging-only immutable publisher trust paths. No request/config/env override.
 * Customer installations retain their legacy layout pending a separate migration.
 */
final class PublisherTrustPaths
{
    private const STAGING = '/www/wwwroot/baramastaging.tonsamith.com';
    private const ROOT = '/etc/barama/staging/publisher-trust';
    private const KEY_NAME = 'update-publisher-v2-keys.json';
    private const FEED_NAME = 'update-feed-v2.json';

    public static function isProtectedStaging(): bool
    {
        return defined('BASE_PATH') && BASE_PATH === self::STAGING;
    }

    public static function directory(): string
    {
        return self::isProtectedStaging() ? self::ROOT : APP_PATH . '/config';
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
        return self::isProtectedStaging() && self::rootControlledDirectory(self::ROOT);
    }

    /** Reject symlinks, missing ancestors, and non-root-writable directory chains. */
    public static function rootControlledDirectory(string $directory): bool
    {
        if ($directory === '' || $directory[0] !== '/' || $directory === '/'
            || str_contains($directory, '//') || str_ends_with($directory, '/')
            || str_contains($directory, '/./') || str_contains($directory, '/../')) {
            return false;
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

    /** Called by all consumers of public publisher key and feed configuration. */
    public static function safeTrustFile(string $path, int $max): bool
    {
        if ($max < 1 || $max > 65536) return false;
        if (self::isProtectedStaging()) {
            if (!in_array($path, [self::keys(), self::feed()], true)
                || !self::rootControlledDirectory(self::ROOT)) return false;
        }
        clearstatcache(true, $path);
        if (is_link($path) || !is_file($path) || !is_readable($path)) return false;
        $st = @lstat($path);
        if (!is_array($st) || $st['size'] < 1 || $st['size'] > $max
            || (($st['mode'] & 0022) !== 0)) return false;
        if (self::isProtectedStaging() && $st['uid'] !== 0) return false;
        return true;
    }

    public static function assertProtectedDirectory(): void
    {
        if (!self::protectedDirectoryReady()) {
            throw new RuntimeException('PUBLISHER_TRUST_PROTECTED_DIRECTORY_UNAVAILABLE');
        }
    }
}
