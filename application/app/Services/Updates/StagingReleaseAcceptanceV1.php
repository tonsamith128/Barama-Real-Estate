<?php
declare(strict_types=1);
namespace Barama\Services\Updates;

use RuntimeException;

/**
 * Read-only Phase 4F staged release acceptance, not installation authorization.
 * Owner-supplied off-server SHA-256 evidence is required; no TOFU fallback.
 */
final class StagingReleaseAcceptanceV1
{
    public static function inspect(string $root, ?string $independentCatalogSha256): array
    {
        $ready = StagingUpdateReadinessV1::inspect($root);
        $signed = StagingSignedReleasePreflightV1::inspect($root);
        $result = [
            'status' => 'RELEASE_ACCEPTANCE_BLOCKED',
            'site' => $ready['site'],
            'catalog_matches_supplied_fingerprint' => false,
            'signed_release_verified' => false,
            'release_digest_verified' => false,
            'file_plan_verified' => false,
            'components_present' => $ready['components_present'],
            'candidate_version' => '',
            'blockers' => [],
            'ready_to_install' => false,
            'automatic_update_enabled' => false,
            'website_writes' => false,
            'database_connections' => false,
            'database_writes' => false,
            'network_requests' => false,
        ];
        $keyPath = $root . '/application/config/update-publisher-v2-keys.json';
        if (!is_string($independentCatalogSha256)
            || preg_match('/^[a-f0-9]{64}$/D', $independentCatalogSha256) !== 1) {
            $result['blockers'][] = 'INDEPENDENT_PUBLISHER_FINGERPRINT_REQUIRED';
        } elseif (is_link($keyPath) || !is_file($keyPath) || !is_readable($keyPath)
            || filesize($keyPath) < 1 || filesize($keyPath) > 65536
            || ((fileperms($keyPath) & 0022) !== 0)) {
            $result['blockers'][] = 'TRUSTED_PUBLISHER_CATALOG_UNAVAILABLE';
        } elseif (!hash_equals($independentCatalogSha256, (string)hash_file('sha256', $keyPath))) {
            $result['blockers'][] = 'INDEPENDENT_PUBLISHER_FINGERPRINT_MISMATCH';
        } else {
            // Matches independently supplied bytes, not a claim that the source
            // of the expected fingerprint was independently authenticated.
            $result['catalog_matches_supplied_fingerprint'] = true;
        }
        foreach ($ready['blockers'] as $blocker) {
            if (!in_array($blocker, ['REAL_DATABASE_RESTORE_NOT_VERIFIED',
                'LIVE_UPDATE_ROLLBACK_NOT_VERIFIED', 'RELEASE_ENTITLEMENT_AND_SIGNATURE_NOT_VERIFIED'], true)) {
                $result['blockers'][] = $blocker;
            }
        }
        foreach ($signed['blockers'] as $blocker) {
            if (!in_array($blocker, ['PUBLISHER_ENTITLEMENT_NOT_RECHECKED',
                'REAL_DATABASE_RESTORE_NOT_VERIFIED', 'LIVE_UPDATE_ROLLBACK_NOT_VERIFIED'], true)) {
                $result['blockers'][] = $blocker;
            }
        }
        $result['signed_release_verified'] = $signed['publisher_signature_verified'];
        $result['release_digest_verified'] = $signed['archive_digest_verified'];
        $result['file_plan_verified'] = $signed['file_plan_verified'];
        $result['candidate_version'] = $signed['candidate_version'];
        // No current-request real entitlement or full live rollback evidence.
        // These blockers cannot be removed by a fixture, CLI flag, or CI result.
        $result['blockers'][] = 'LIVE_PUBLISHER_ENTITLEMENT_RECHECK_REQUIRED';
        $result['blockers'][] = 'STAGING_DATABASE_ROLLBACK_ACCEPTANCE_REQUIRED';
        $result['blockers'][] = 'LIVE_CODE_AND_DATABASE_UPDATE_ACCEPTANCE_REQUIRED';
        $result['blockers'] = array_values(array_unique($result['blockers']));
        if ($result['catalog_matches_supplied_fingerprint']
            && $result['signed_release_verified']
            && $result['release_digest_verified']
            && $result['file_plan_verified']
            && $result['components_present']) {
            $result['status'] = 'SIGNED_STAGED_RELEASE_INSPECTED_NOT_INSTALLABLE';
        }
        return $result;
    }
}
