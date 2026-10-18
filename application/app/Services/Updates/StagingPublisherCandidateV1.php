<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

/**
 * Read-only candidate reconciliation, for the authenticated CSRF-protected
 * "Check for updates" POST after TrustedReleaseFeed::check() completes.
 *
 * No install, download, SQL, credentials, persistence, or remote I/O.
 * A matched signed candidate still NEVER authorizes automatic updates.
 */
final class StagingPublisherCandidateV1
{
    private const STAGING = '/www/wwwroot/baramastaging.tonsamith.com';

    /** @param array<string,mixed> $feed TrustedReleaseFeed::check() result from the current request */
    public static function inspect(string $root, string $installedVersion, array $feed): array
    {
        $result = [
            'status' => 'STAGING_ONLY',
            'publisher_release_matched_to_staged_zip' => false,
            'publisher_entitlement_confirmed_for_discovery' => false,
            'signed_zip_verified' => false,
            'candidate_version' => '',
            'blockers' => [
                'FULL_DATABASE_RECOVERY_NOT_PROVEN',
                'LIVE_UPDATE_ROLLBACK_NOT_PROVEN',
                'AUTOMATIC_INSTALLATION_DISABLED',
            ],
            'ready_to_install' => false,
            'auto_install_enabled' => false,
            'website_writes' => false,
            'database_connections' => false,
            'database_writes' => false,
        ];
        // The controller calls this only after the protected publisher POST.
        // Do not make it available as a separate unauthenticated route.
        if ($root !== self::STAGING || !defined('BASE_PATH') || BASE_PATH !== $root
            || !defined('APP_PATH') || APP_PATH !== $root . '/application') {
            return $result;
        }
        $result['status'] = 'PUBLISHER_CANDIDATE_NOT_CONFIRMED';
        $sha = $feed['archive_sha256'] ?? null;
        $version = $feed['version'] ?? null;
        if (($feed['status'] ?? null) !== 'available'
            || ($feed['entitlement'] ?? null) !== 'Eligible'
            || ($feed['feed'] ?? null) !== 'Signed release verified'
            || !is_string($sha) || preg_match('/^[a-f0-9]{64}$/D', $sha) !== 1
            || !is_string($version) || $version === ''
            || version_compare($version, $installedVersion, '<=')) {
            return $result;
        }
        $result['publisher_entitlement_confirmed_for_discovery'] = true;
        $result['candidate_version'] = $version;
        $result['status'] = 'SIGNED_ZIP_MISSING_OR_REJECTED';
        try {
            $verified = SignedReleaseInspector::inspect($installedVersion);
            $actualSha = $verified['archive_sha256'] ?? null;
            $actualVersion = $verified['version'] ?? null;
            if (!is_string($actualSha) || preg_match('/^[a-f0-9]{64}$/D', $actualSha) !== 1
                || !is_string($actualVersion)) {
                return $result;
            }
            $result['signed_zip_verified'] = true;
            if (!hash_equals($sha, $actualSha) || !hash_equals($version, $actualVersion)) {
                $result['status'] = 'SIGNED_ZIP_DOES_NOT_MATCH_PUBLISHER_RELEASE';
                return $result;
            }
            $result['status'] = 'PUBLISHER_RELEASE_AND_STAGED_ZIP_MATCH_READ_ONLY';
            $result['publisher_release_matched_to_staged_zip'] = true;
        } catch (\Throwable) {
            // No server path, publisher response, stack trace or license secrets.
            return $result;
        }
        return $result;
    }
}
