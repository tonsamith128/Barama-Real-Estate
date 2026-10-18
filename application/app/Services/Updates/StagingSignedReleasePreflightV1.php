<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;

/**
 * Offline signed-release staging check: no download, SQL, install or write.
 *
 * Staging only. Publisher signature comes exclusively from the pinned
 * TrustedReleaseFeed verifier; the file plan still never authorizes installation.
 */
final class StagingSignedReleasePreflightV1
{
    private const STAGING = '/www/wwwroot/baramastaging.tonsamith.com';

    /** @return array<string,mixed> */
    public static function inspect(string $siteRoot): array
    {
        if (PHP_SAPI !== 'cli') {
            throw new RuntimeException('SIGNED_PREFLIGHT_CLI_ONLY');
        }
        if (is_link($siteRoot) || !is_dir($siteRoot) || realpath($siteRoot) !== $siteRoot) {
            throw new RuntimeException('SIGNED_PREFLIGHT_ROOT_UNTRUSTED');
        }
        $fixture = preg_match('~^/tmp/barama-signed-stage-[a-f0-9]{12,32}/site$~D', $siteRoot) === 1;
        if ($siteRoot !== self::STAGING && !$fixture) {
            throw new RuntimeException('SIGNED_PREFLIGHT_STAGING_ONLY');
        }
        if (!defined('APP_PATH') || APP_PATH !== $siteRoot . '/application'
            || !defined('BASE_PATH') || BASE_PATH !== $siteRoot) {
            throw new RuntimeException('SIGNED_PREFLIGHT_RUNTIME_BINDING_INVALID');
        }

        $result = [
            'status' => 'SIGNED_STAGING_INSPECTION_ONLY',
            'site' => $fixture ? 'DISPOSABLE_FIXTURE' : 'STAGING_ONLY',
            'release_staged' => false,
            'publisher_signature_verified' => false,
            'archive_digest_verified' => false,
            'file_plan_verified' => false,
            'candidate_version' => '',
            'candidate_files' => 0,
            'blockers' => [],
            'publisher_entitlement_verified' => false,
            'database_restore_verified' => false,
            'live_rollback_verified' => false,
            'ready_to_install' => false,
            'automatic_update_enabled' => false,
            'website_writes' => false,
            'database_connections' => false,
            'database_writes' => false,
        ];
        $staged = APP_PATH . '/storage/app/updates/staged';
        $manifest = $staged . '/envelope.json';
        $archive = $staged . '/release.zip';
        $versionPath = $siteRoot . '/VERSION';

        // Reject symlinks in every staged path component, not only leaf files.
        foreach ([
            APP_PATH,
            APP_PATH . '/storage', APP_PATH . '/storage/app',
            APP_PATH . '/storage/app/updates', $staged, $manifest, $archive,
            $versionPath,
        ] as $path) {
            if (is_link($path)) {
                $result['blockers'][] = 'STAGED_RELEASE_PATH_UNTRUSTED';
                return self::blocked($result);
            }
        }
        if (!is_file($versionPath) || !is_readable($versionPath)
            || filesize($versionPath) < 1 || filesize($versionPath) > 128) {
            $result['blockers'][] = 'INSTALLED_VERSION_UNAVAILABLE';
            return self::blocked($result);
        }
        $installed = trim((string)file_get_contents($versionPath));
        if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][0-9A-Za-z.-]+)?$/D', $installed)) {
            $result['blockers'][] = 'INSTALLED_VERSION_INVALID';
            return self::blocked($result);
        }
        if (!is_file($manifest) || !is_file($archive)) {
            $result['blockers'][] = 'SIGNED_RELEASE_NOT_STAGED';
            return self::blocked($result);
        }
        $result['release_staged'] = true;
        if (!extension_loaded('sodium') || !class_exists(\ZipArchive::class)) {
            $result['blockers'][] = 'CRYPTO_OR_ZIP_UNAVAILABLE';
            return self::blocked($result);
        }
        try {
            // Re-use the existing independent Publisher V2 Ed25519 verifier and
            // signed archive inspector; do not invent a second trust format.
            $verified = SignedReleaseInspector::inspectPaths($manifest, $archive, $installed);
            $plan = VerifiedReleaseFilePlanV2::inspect($manifest, $archive, $installed);
            if (($plan['status'] ?? '') !== 'verified_plan_only'
                || ($plan['installation_enabled'] ?? true) !== false
                || !is_string($verified['archive_sha256'] ?? null)
                || !hash_equals($verified['archive_sha256'], $plan['archive_sha256'] ?? '')
                || ($verified['version'] ?? null) !== ($plan['target_version'] ?? null)) {
                throw new RuntimeException('SIGNED_PREFLIGHT_PLAN_MISMATCH');
            }
            $result['publisher_signature_verified'] = true;
            $result['archive_digest_verified'] = true;
            $result['file_plan_verified'] = true;
            $result['candidate_version'] = $plan['target_version'];
            $result['candidate_files'] = $plan['files'];
        } catch (\Throwable) {
            // Do not expose sensitive server paths, signed payloads or PHP trace.
            $result['blockers'][] = 'SIGNED_RELEASE_OR_PLAN_REJECTED';
        }
        return self::blocked($result);
    }

    private static function blocked(array $result): array
    {
        // Signatures authenticate bytes, not commercial entitlement or
        // recoverability. These gates MUST remain independent.
        $result['blockers'][] = 'PUBLISHER_ENTITLEMENT_NOT_RECHECKED';
        $result['blockers'][] = 'REAL_DATABASE_RESTORE_NOT_VERIFIED';
        $result['blockers'][] = 'LIVE_UPDATE_ROLLBACK_NOT_VERIFIED';
        return $result;
    }
}
