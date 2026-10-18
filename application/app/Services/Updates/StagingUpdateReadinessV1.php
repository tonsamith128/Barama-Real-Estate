<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;

/**
 * Phase 4A read-only staging upgrade readiness check.
 * No release install, SQL access, backups, migrations or filesystem writes.
 * Never accepts the production website path.
 */
final class StagingUpdateReadinessV1
{
    private const STAGING = '/www/wwwroot/baramastaging.tonsamith.com';
    private const REQUIRED = [
        'SignedReleaseInspector.php',
        'TrustedReleaseFeed.php',
        'VerifiedReleaseFilePlanV2.php',
        'VerifiedUpdateBackupV2.php',
        'PrivateDatabaseBackupV2.php',
        'IsolatedUpdateTransactionV1.php',
        'IsolatedMysqlRecoveryV1.php',
    ];

    /** @return array<string,mixed> */
    public static function inspect(string $siteRoot): array
    {
        if (PHP_SAPI !== 'cli') {
            throw new RuntimeException('READINESS_CLI_ONLY');
        }
        if ($siteRoot === '' || str_contains($siteRoot, '..') || is_link($siteRoot)
            || !is_dir($siteRoot) || realpath($siteRoot) !== $siteRoot) {
            throw new RuntimeException('READINESS_ROOT_UNSAFE');
        }
        $fixture = preg_match('~^/tmp/barama-staging-readiness-[a-f0-9]{12,32}/site$~D', $siteRoot) === 1
            || (getenv('GITHUB_ACTIONS') === 'true'
                && getenv('GITHUB_REPOSITORY') === 'tonsamith128/Barama-Real-Estate'
                && preg_match('~^/tmp/barama-release-acceptance-[a-f0-9]{16}/site$~D', $siteRoot) === 1);
        if ($siteRoot !== self::STAGING && !$fixture) {
            throw new RuntimeException('READINESS_STAGING_ONLY');
        }
        $versionPath = $siteRoot . '/VERSION';
        if (!self::regular($versionPath, 128)) {
            throw new RuntimeException('READINESS_VERSION_UNAVAILABLE');
        }
        $version = trim((string) file_get_contents($versionPath));
        if (preg_match('/^[0-9A-Za-z][0-9A-Za-z.+-]{1,40}$/D', $version) !== 1) {
            throw new RuntimeException('READINESS_VERSION_INVALID');
        }
        $dir = $siteRoot . '/application/app/Services/Updates';
        if (is_link($dir) || !is_dir($dir)) {
            throw new RuntimeException('READINESS_COMPONENT_DIR_UNAVAILABLE');
        }
        $components = [];
        foreach (self::REQUIRED as $name) {
            $path = $dir . '/' . $name;
            $valid = self::regular($path, 200000);
            $components[$name] = [
                'present' => $valid,
                'sha256' => $valid ? hash_file('sha256', $path) : null,
            ];
        }
        $allPresent = array_reduce($components, static fn(bool $ok, array $v): bool => $ok && $v['present'], true);
        // Presence alone is not trust. Only the original signed verifier can
        // authenticate an issued release against publisher and entitlement.
        $envelope = $siteRoot . '/application/storage/app/updates/staged/envelope.json';
        $archive = $siteRoot . '/application/storage/app/updates/staged/release.zip';
        $staged = self::regular($envelope, 16384) && self::regular($archive, 536870912);
        $runtime = [
            'sodium' => extension_loaded('sodium'),
            'zip' => class_exists('ZipArchive'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
        ];
        $free = @disk_free_space($siteRoot);
        $spaceOK = (is_float($free) || is_int($free)) && $free >= 134217728;
        $blocking = [];
        if (!$allPresent) $blocking[] = 'UPDATER_COMPONENTS_MISSING';
        if (!$runtime['sodium'] || !$runtime['zip']) $blocking[] = 'SIGNED_ZIP_RUNTIME_UNAVAILABLE';
        if (!$runtime['pdo_mysql']) $blocking[] = 'DATABASE_DRIVER_UNAVAILABLE';
        if (!$spaceOK) $blocking[] = 'FREE_DISK_SPACE_UNVERIFIED';
        if (!$staged) $blocking[] = 'SIGNED_RELEASE_NOT_STAGED';
        $blocking[] = 'REAL_DATABASE_RESTORE_NOT_VERIFIED';
        $blocking[] = 'LIVE_UPDATE_ROLLBACK_NOT_VERIFIED';
        $blocking[] = 'RELEASE_ENTITLEMENT_AND_SIGNATURE_NOT_VERIFIED';
        return [
            'status' => 'READ_ONLY_STAGING_PRECHECK',
            'site' => $fixture ? 'DISPOSABLE_FIXTURE' : 'STAGING_ONLY',
            'installed_version' => $version,
            'components' => $components,
            'components_present' => $allPresent,
            'runtime' => $runtime,
            'signed_release_files_present' => $staged,
            'signed_release_verified' => false,
            'free_space_at_least_128mb' => $spaceOK,
            'blockers' => $blocking,
            'ready_to_install' => false,
            'automatic_update_enabled' => false,
            'database_connections' => false,
            'database_writes' => false,
            'website_writes' => false,
        ];
    }

    private static function regular(string $path, int $maxBytes): bool
    {
        if (is_link($path) || !is_file($path) || !is_readable($path)) return false;
        $stat = @stat($path);
        return is_array($stat) && $stat['size'] > 0 && $stat['size'] <= $maxBytes;
    }
}
