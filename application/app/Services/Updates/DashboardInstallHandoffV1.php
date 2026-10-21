<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

/**
 * Pure, fail-closed staging request review contract.
 *
 * This method takes untrusted session/cache hints, not an install command.
 * It never gives a web process authority to invoke root, the shell, migrations,
 * the filesystem or a background job. A privileged worker MUST independently
 * re-evaluate the license, signed envelope, stage bytes, site version, backups,
 * lock, administrator consent and recovery guarantees before installing.
 */
final class DashboardInstallHandoffV1
{
    private const STAGING = '/www/wwwroot/baramastaging.tonsamith.com';
    private const SHA256 = '/^[a-f0-9]{64}$/D';
    private const VERSION = '/^\d{1,4}\.\d{1,4}\.\d{1,4}(?:-[A-Za-z0-9.]{1,24})?$/D';
    private const MAX_AGE = 120;

    /**
     * @param array<string,mixed> $feed Result saved by the authenticated publisher discovery POST
     * @param array<string,mixed> $candidate Result of StagingPublisherCandidateV1::inspect()
     * @return array<string,mixed> Non-authoritative display-only review outcome
     */
    public static function review(
        string $siteRoot,
        string $installedVersion,
        array $feed,
        array $candidate,
        ?int $now = null
    ): array {
        $result = [
            'state' => 'BLOCKED',
            'reason' => 'STAGING_ONLY',
            'candidate_version' => '',
            'archive_sha256' => '',
            'candidate_reviewed' => false,
            'install_authorized' => false,
            'install_executed' => false,
            'root_worker_invoked' => false,
            'website_writes' => false,
            'database_writes' => false,
        ];
        // Deliberately never allow production, arbitrary paths, aliases, or symlinks
        // to be decided from request data. This does NOT replace worker path checks.
        if ($siteRoot !== self::STAGING) return $result;
        $result['reason'] = 'INVALID_VERSION';
        if (preg_match(self::VERSION, $installedVersion) !== 1) return $result;
        $target = $feed['version'] ?? null;
        if (!is_string($target) || preg_match(self::VERSION, $target) !== 1
            || version_compare($target, $installedVersion, '<=')
        ) return $result;
        $result['reason'] = 'PUBLISHER_NOT_VERIFIED';
        $checkedAt = $feed['at'] ?? null;
        $clock = $now ?? time();
        if (!is_int($checkedAt) || $checkedAt > $clock + 30
            || $clock - $checkedAt > self::MAX_AGE
            || ($feed['status'] ?? null) !== 'available'
            || ($feed['entitlement'] ?? null) !== 'Eligible'
            || ($feed['feed'] ?? null) !== 'Signed release verified'
        ) return $result;
        $sha = $feed['archive_sha256'] ?? null;
        if (!is_string($sha) || preg_match(self::SHA256, $sha) !== 1) return $result;
        $result['reason'] = 'STAGED_SIGNED_CANDIDATE_NOT_MATCHED';
        if (($candidate['status'] ?? null) !== 'PUBLISHER_RELEASE_AND_STAGED_ZIP_MATCH_READ_ONLY'
            || ($candidate['publisher_release_matched_to_staged_zip'] ?? null) !== true
            || ($candidate['publisher_entitlement_confirmed_for_discovery'] ?? null) !== true
            || ($candidate['signed_zip_verified'] ?? null) !== true
            || ($candidate['candidate_version'] ?? null) !== $target
        ) return $result;
        // The source candidate intentionally declares its remaining blockers.
        // Never promote its discovery check to the authority to install.
        $result['state'] = 'CANDIDATE_VERIFIED_FOR_REVIEW';
        $result['reason'] = 'PRIVILEGED_WORKER_NOT_AUTHORIZED';
        $result['candidate_version'] = $target;
        $result['archive_sha256'] = $sha;
        $result['candidate_reviewed'] = true;
        return $result;
    }
}
