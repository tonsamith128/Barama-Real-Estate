<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use Barama\Core\Installation;
use RuntimeException;
use Throwable;

/**
 * CLI-only, staging-only READ-ONLY signed-update preparation inspector.
 * Never downloads, backs up, extracts, runs migrations, or authorizes installs.
 * A receipt proves bytes were preserved, NOT that recovery succeeded.
 */
final class OneClickOfflinePreparationV1
{
    private const STAGING = '/www/wwwroot/baramastaging.tonsamith.com';
    private const SHA256 = '/^[a-f0-9]{64}$/D';

    /**
     * Paths are explicit root-private receipt directories from earlier independent jobs.
     * The expected DB-name hash is an independently supplied comparison only;
     * it is NOT by itself authoritative database identity evidence.
     *
     * @return array<string,mixed> Non-sensitive audit summary, never authorization.
     */
    public static function inspect(
        string $siteRoot,
        string $codeBackupDir = '',
        string $codeReceiptSha256 = '',
        string $dbBackupDir = '',
        string $dbReceiptSha256 = '',
        string $expectedDbNameSha256 = ''
    ): array {
        if (PHP_SAPI !== 'cli') {
            throw new RuntimeException('ONECLICK_PREFLIGHT_CLI_ONLY');
        }
        if ($siteRoot !== self::STAGING || !self::safeDirectory($siteRoot, false)
            || !defined('BASE_PATH') || BASE_PATH !== $siteRoot
            || !defined('APP_PATH') || APP_PATH !== $siteRoot . '/application') {
            throw new RuntimeException('ONECLICK_PREFLIGHT_STAGING_ONLY');
        }

        $blockers = [
            'LIVE_PUBLISHER_ENTITLEMENT_RECHECK_REQUIRED',
            'INDEPENDENT_OPERATOR_APPROVAL_REQUIRED',
            'PRIVILEGED_INSTALLER_NOT_IMPLEMENTED',
            'DATABASE_RESTORE_REHEARSAL_NOT_PROVEN',
            'CODE_AND_DATABASE_CRASH_RECOVERY_NOT_PROVEN',
        ];
        $result = [
            'status' => 'REVIEW_ONLY_NOT_INSTALLABLE',
            'site_version_consistent' => false,
            'signed_file_plan_verified' => false,
            'code_backup_receipt_verified' => false,
            'db_backup_receipt_verified' => false,
            'db_identity_independently_verified' => false,
            'restore_rehearsal_verified' => false,
            'installation_enabled' => false,
            'release_sha256' => '',
            'target_version' => '',
            'blockers' => [],
            'network_requests' => false,
            'website_writes' => false,
            'database_connections' => false,
            'database_writes' => false,
            'root_worker_started' => false,
        ];

        try {
            $metadata = Installation::metadata();
            $version = $metadata['version'] ?? null;
            if (is_link($siteRoot . '/VERSION') || !is_file($siteRoot . '/VERSION')) {
                throw new RuntimeException('VERSION_FILE_UNTRUSTED');
            }
            $packageVersion = @file_get_contents($siteRoot . '/VERSION');
            if (!is_string($version) || !is_string($packageVersion)
                || trim($packageVersion) !== $version
                || !defined('APP_VERSION') || APP_VERSION !== $version
                || preg_match('/^\d{1,4}\.\d{1,4}\.\d{1,4}(?:-[A-Za-z0-9.]{1,24})?$/D', $version) !== 1) {
                throw new RuntimeException('VERSION_BINDING_MISMATCH');
            }
            $result['site_version_consistent'] = true;
        } catch (Throwable) {
            $blockers[] = 'VERSION_BINDING_MISMATCH';
            $result['blockers'] = $blockers;
            return $result;
        }

        // SignedReleaseInspector::inspectPaths is already called internally.
        // Never accept a caller-supplied "verified" flag in place of actual bytes.
        try {
            $locations = SignedReleaseInspector::locations();
            $plan = VerifiedReleaseFilePlanV2::inspect(
                $locations['manifest'], $locations['archive'], $version
            );
            if (($plan['status'] ?? null) !== 'verified_plan_only'
                || ($plan['installation_enabled'] ?? null) !== false
                || ($plan['installed_version'] ?? null) !== $version
                || !self::sha($plan['archive_sha256'] ?? null)
                || !is_string($plan['target_version'] ?? null)
                || version_compare($plan['target_version'], $version, '<=')) {
                throw new RuntimeException('SIGNED_PLAN_UNTRUSTED');
            }
            $result['signed_file_plan_verified'] = true;
            $result['release_sha256'] = $plan['archive_sha256'];
            $result['target_version'] = $plan['target_version'];
        } catch (Throwable) {
            $blockers[] = 'NEWER_SIGNED_RELEASE_AND_PLAN_NOT_VERIFIED';
            $result['blockers'] = array_values(array_unique($blockers));
            return $result;
        }

        // Existing read-only verifier rehashes every backup copy. Receipt
        // content must ALSO bind to this precise signed archive and versions.
        try {
            if (!self::sha($codeReceiptSha256)
                || !self::safeDirectory($codeBackupDir, true)
                || !VerifiedUpdateBackupV2::verify($codeBackupDir, $codeReceiptSha256)) {
                throw new RuntimeException('CODE_BACKUP_UNVERIFIED');
            }
            $receipt = self::readReceipt($codeBackupDir);
            if (($receipt['release_archive_sha256'] ?? null) !== $plan['archive_sha256']
                || ($receipt['installed_version'] ?? null) !== $version
                || ($receipt['target_version'] ?? null) !== $plan['target_version']
                || ($receipt['state'] ?? null) !== 'prepared_not_installed'
                || !is_array($receipt['files'] ?? null)
                || count($receipt['files']) !== count($plan['targets'])) {
                throw new RuntimeException('CODE_BACKUP_WRONG_RELEASE');
            }
            $expected = [];
            foreach ($plan['targets'] as $target) {
                $expected[$target['path']] = $target;
            }
            foreach ($receipt['files'] as $entry) {
                if (!is_array($entry) || !is_string($entry['path'] ?? null)
                    || !array_key_exists($entry['path'], $expected)) {
                    throw new RuntimeException('CODE_BACKUP_COVERAGE_CHANGED');
                }
                $target = $expected[$entry['path']];
                if (($entry['baseline'] ?? null) !== $target['installed_sha256']
                    || ($entry['new_sha256'] ?? null) !== $target['archive_sha256']
                    || ($entry['exists'] ?? null) !== ($target['installed_sha256'] !== null)) {
                    throw new RuntimeException('CODE_BACKUP_BASELINE_MISMATCH');
                }
                unset($expected[$entry['path']]);
            }
            if ($expected !== []) {
                throw new RuntimeException('CODE_BACKUP_COVERAGE_INCOMPLETE');
            }
            $result['code_backup_receipt_verified'] = true;
        } catch (Throwable) {
            $blockers[] = 'EXACT_RELEASE_CODE_BACKUP_NOT_VERIFIED';
        }

        try {
            if (!self::sha($dbReceiptSha256)
                || !self::safeDirectory($dbBackupDir, true)
                || !PrivateDatabaseBackupV2::verify($dbBackupDir, $dbReceiptSha256)) {
                throw new RuntimeException('DB_BACKUP_UNVERIFIED');
            }
            $receipt = self::readReceipt($dbBackupDir);
            if (($receipt['state'] ?? null) !== 'backup_not_restored'
                || ($receipt['restore_tested'] ?? null) !== false
                || !self::sha($expectedDbNameSha256)
                || !hash_equals($expectedDbNameSha256,
                    (string) ($receipt['database_name_sha256'] ?? ''))) {
                throw new RuntimeException('DB_BACKUP_IDENTITY_NOT_CONFIRMED');
            }
            $result['db_backup_receipt_verified'] = true;
            // Do NOT set db_identity_independently_verified on a caller-provided hash.
        } catch (Throwable) {
            $blockers[] = 'DATABASE_BACKUP_IDENTITY_OR_RECEIPT_NOT_VERIFIED';
        }

        $result['blockers'] = array_values(array_unique($blockers));
        return $result;
    }

    private static function sha(mixed $value): bool
    {
        return is_string($value) && preg_match(self::SHA256, $value) === 1;
    }

    private static function safeDirectory(string $path, bool $private): bool
    {
        if ($path === '' || !str_starts_with($path, '/') || str_contains($path, '..')
            || is_link($path) || !is_dir($path) || realpath($path) !== $path) {
            return false;
        }
        if ($private) {
            if (!str_starts_with($path, '/root/')
                || !preg_match('~^/root/[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+){0,5}$~D', $path)) {
                return false;
            }
            $cursor = '/root';
            foreach (explode('/', substr($path, strlen('/root/'))) as $part) {
                $cursor .= '/' . $part;
                if (is_link($cursor) || !is_dir($cursor)) return false;
                $stat = @stat($cursor);
                if (!is_array($stat) || $stat['uid'] !== 0 || ($stat['mode'] & 0077) !== 0) return false;
            }
        }
        return true;
    }

    /** @return array<string,mixed> */
    private static function readReceipt(string $directory): array
    {
        $file = $directory . '/receipt.json';
        if (is_link($file) || !is_file($file) || filesize($file) > 1048576
            || (fileperms($file) & 0077) !== 0) {
            throw new RuntimeException('ONECLICK_RECEIPT_UNTRUSTED');
        }
        $data = json_decode((string) file_get_contents($file), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new RuntimeException('ONECLICK_RECEIPT_INVALID');
        return $data;
    }

    private function __construct()
    {
    }
}
