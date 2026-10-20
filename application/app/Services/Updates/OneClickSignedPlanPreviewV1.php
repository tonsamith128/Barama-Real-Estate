<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use Barama\Core\Installation;
use RuntimeException;

/**
 * Authenticated-admin update preview, never an installation authorization.
 *
 * Uses the existing cryptographically verified V2 archive and the complete
 * read-only file plan. No SQL, downloads, extraction, network, backup or writes.
 */
final class OneClickSignedPlanPreviewV1
{
    private const MAX_LISTED_PATHS = 40;

    /** @return array<string,mixed> Safe, bounded, non-secret summary. */
    public static function inspect(): array
    {
        if (!defined('BASE_PATH') || !defined('APP_PATH') || !defined('APP_VERSION')
            || !Installation::isInstalled()) {
            throw new RuntimeException('UPDATE_PREVIEW_INSTALLATION_UNAVAILABLE');
        }
        $installed = Installation::metadata()['version'] ?? null;
        if (!is_string($installed) || !hash_equals((string) APP_VERSION, $installed)
            || !preg_match('/^\d{1,4}\.\d{1,4}\.\d{1,4}(?:-[A-Za-z0-9.]{1,24})?$/D', $installed)) {
            throw new RuntimeException('UPDATE_PREVIEW_INSTALLED_VERSION_MISMATCH');
        }

        $loc = SignedReleaseInspector::locations();
        $plan = VerifiedReleaseFilePlanV2::inspect($loc['manifest'], $loc['archive'], $installed);
        if (($plan['status'] ?? null) !== 'verified_plan_only'
            || ($plan['installation_enabled'] ?? null) !== false
            || ($plan['installed_version'] ?? null) !== $installed
            || !is_string($plan['target_version'] ?? null)
            || version_compare($plan['target_version'], $installed, '<=')
            || !is_string($plan['archive_sha256'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', $plan['archive_sha256'])
            || !is_array($plan['targets'] ?? null)
            || count($plan['targets']) !== ($plan['files'] ?? null)
            || !is_int($plan['bytes'] ?? null)
            || $plan['bytes'] < 1) {
            throw new RuntimeException('UPDATE_PREVIEW_SIGNED_PLAN_UNAVAILABLE');
        }
        $added = 0;
        $changed = 0;
        $unchanged = 0;
        $listed = [];
        foreach ($plan['targets'] as $item) {
            if (!is_array($item) || !is_string($item['path'] ?? null)
                || !is_string($item['archive_sha256'] ?? null)) {
                throw new RuntimeException('UPDATE_PREVIEW_TARGET_INVALID');
            }
            $path = $item['path'];
            if (strlen($path) < 1 || strlen($path) > 512
                || !preg_match('~^[A-Za-z0-9_./-]+$~D', $path)) {
                throw new RuntimeException('UPDATE_PREVIEW_TARGET_INVALID');
            }
            $baseline = $item['installed_sha256'] ?? null;
            if ($baseline !== null && !is_string($baseline)) {
                throw new RuntimeException('UPDATE_PREVIEW_BASELINE_INVALID');
            }
            $kind = $baseline === null ? 'New'
                : (hash_equals($baseline, $item['archive_sha256']) ? 'Unchanged' : 'Replace');
            if ($kind === 'New') ++$added;
            elseif ($kind === 'Replace') ++$changed;
            else ++$unchanged;

            if (count($listed) < self::MAX_LISTED_PATHS) {
                $listed[] = ['path' => $path, 'change' => $kind];
            }
        }

        // A signed package is code-only; SQL migration paths are explicitly
        // rejected by the existing file planner. This does NOT certify a DB update.
        return [
            'status' => 'SIGNED_RELEASE_PLAN_VERIFIED_NOT_INSTALLED',
            'installed_version' => $installed,
            'target_version' => $plan['target_version'],
            'archive_sha256' => $plan['archive_sha256'],
            'file_count' => $plan['files'],
            'changed_files' => $changed,
            'new_files' => $added,
            'unchanged_files' => $unchanged,
            'bytes' => $plan['bytes'],
            'listed_paths' => $listed,
            'paths_truncated' => count($plan['targets']) > self::MAX_LISTED_PATHS,
            'installation_enabled' => false,
            'db_migrations_verified' => false,
            'rollback_verified' => false,
        ];
    }

    /**
     * Permit showing only this exact signed preview while the stored result is
     * fresh and its archive SHA matches independently rehashed staged bytes.
     * Session data never authorizes installing a release.
     *
     * @param mixed $preview
     * @return array<string,mixed>|null
     */
    public static function display(mixed $preview, int $now, ?string $actualArchiveSha): ?array
    {
        if (!is_array($preview)
            || ($preview['status'] ?? null) !== 'SIGNED_RELEASE_PLAN_VERIFIED_NOT_INSTALLED'
            || ($preview['installation_enabled'] ?? null) !== false
            || ($preview['db_migrations_verified'] ?? null) !== false
            || ($preview['rollback_verified'] ?? null) !== false
            || !is_int($preview['verified_at'] ?? null)
            || $preview['verified_at'] > $now || $preview['verified_at'] < $now - 600
            || ($preview['site_version'] ?? null) !== (defined('APP_VERSION') ? APP_VERSION : null)
            || !is_string($preview['archive_sha256'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', $preview['archive_sha256'])
            || !is_string($actualArchiveSha)
            || !preg_match('/^[a-f0-9]{64}$/D', $actualArchiveSha)
            || !hash_equals($preview['archive_sha256'], $actualArchiveSha)
            || !is_array($preview['listed_paths'] ?? null)
            || count($preview['listed_paths']) > self::MAX_LISTED_PATHS
            || !is_int($preview['file_count'] ?? null)
            || $preview['file_count'] < 1 || $preview['file_count'] > 8000
            || !is_string($preview['installed_version'] ?? null)
            || !is_string($preview['target_version'] ?? null)
            || $preview['installed_version'] !== APP_VERSION
            || !preg_match('/^\d{1,4}\.\d{1,4}\.\d{1,4}(?:-[A-Za-z0-9.]{1,24})?$/D', $preview['target_version'])
            || version_compare($preview['target_version'], APP_VERSION, '<=')
            || !is_int($preview['changed_files'] ?? null) || $preview['changed_files'] < 0
            || !is_int($preview['new_files'] ?? null) || $preview['new_files'] < 0
            || !is_int($preview['unchanged_files'] ?? null) || $preview['unchanged_files'] < 0
            || $preview['changed_files'] + $preview['new_files']
                + $preview['unchanged_files'] !== $preview['file_count']
            || !is_int($preview['bytes'] ?? null) || $preview['bytes'] < 1
            || !is_bool($preview['paths_truncated'] ?? null)
            || count($preview['listed_paths']) !== min($preview['file_count'], self::MAX_LISTED_PATHS)
            || $preview['paths_truncated'] !== ($preview['file_count'] > self::MAX_LISTED_PATHS)) {
            return null;
        }
        $safe = [];
        $seen = [];
        foreach ($preview['listed_paths'] as $item) {
            if (!is_array($item) || !is_string($item['path'] ?? null)
                || !preg_match('~^[A-Za-z0-9_./-]{1,512}$~D', $item['path'])
                || !in_array($item['change'] ?? null, ['New', 'Replace', 'Unchanged'], true)) {
                return null;
            }
            foreach (explode('/', $item['path']) as $part) {
                if ($part === '' || $part === '.' || $part === '..') return null;
            }
            $key = strtolower($item['path']);
            if (isset($seen[$key])) return null;
            $seen[$key] = true;
            $safe[] = ['path' => $item['path'], 'change' => $item['change']];
        }
        // Do not pass raw session data through to the template.
        return [
            'status' => 'SIGNED_RELEASE_PLAN_VERIFIED_NOT_INSTALLED',
            'installed_version' => is_string($preview['installed_version'] ?? null) ? $preview['installed_version'] : '',
            'target_version' => is_string($preview['target_version'] ?? null) ? $preview['target_version'] : '',
            'file_count' => $preview['file_count'],
            'changed_files' => is_int($preview['changed_files'] ?? null) ? $preview['changed_files'] : 0,
            'new_files' => is_int($preview['new_files'] ?? null) ? $preview['new_files'] : 0,
            'unchanged_files' => is_int($preview['unchanged_files'] ?? null) ? $preview['unchanged_files'] : 0,
            'bytes' => is_int($preview['bytes'] ?? null) ? $preview['bytes'] : 0,
            'listed_paths' => $safe,
            'paths_truncated' => ($preview['paths_truncated'] ?? null) === true,
            'installation_enabled' => false,
        ];
    }

    private function __construct()
    {
    }
}
