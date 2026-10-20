<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;

/**
 * Real signed ZIP + independently signed single-use consent + atomic files.
 * ONLY disposable /tmp tests; explicitly refuses installed customer sites.
 * The production worker also requires coherent SQL recovery and privileges.
 */
final class OneClickAuthorizedFixtureInstallV1
{
    /** @param array<string,mixed> $ownerEnvelope */
    public static function rehearse(
        string $site, string $vault, string $signedEnvelopePath, string $archivePath,
        string $installedVersion, array $ownerEnvelope, int $now,
        ?int $crashAfter = null
    ): array {
        $base = dirname($site);
        if (PHP_SAPI !== 'cli'
            || !preg_match('~^/tmp/barama-oneclick-transaction-fixture-[a-f0-9]{16}$~D', $base)
            || $site !== $base . '/site' || $vault !== $base . '/vault'
            || !defined('BASE_PATH') || BASE_PATH !== $site
            || !defined('APP_PATH') || APP_PATH !== $site . '/application'
            || is_link($base) || is_link($site) || is_link($vault)
            || !is_dir($site) || !is_dir($vault)
            || realpath($base) !== $base || realpath($site) !== $site
            || realpath($vault) !== $vault) {
            throw new RuntimeException('AUTHORIZED_WORKER_FIXTURE_ONLY');
        }
        // The signed ZIP is independently revalidated before the operator
        // nonce is consumed, and AGAIN immediately before the file writes.
        $plan = VerifiedReleaseFilePlanV2::inspect(
            $signedEnvelopePath, $archivePath, $installedVersion
        );
        if (($plan['status'] ?? '') !== 'verified_plan_only'
            || ($plan['installation_enabled'] ?? null) !== false
            || ($plan['installed_version'] ?? '') !== $installedVersion
            || !is_string($plan['target_version'] ?? null)
            || !is_string($plan['archive_sha256'] ?? null)) {
            throw new RuntimeException('AUTHORIZED_WORKER_SIGNED_PLAN_INVALID');
        }
        $receipt = OneClickOwnerConsentFixtureV1::consumeFixture(
            $site, $vault, $ownerEnvelope, $plan['archive_sha256'],
            $installedVersion, $plan['target_version'], $now
        );
        if (($receipt['status'] ?? null) !== 'INDEPENDENT_CONSENT_RECORDED_FIXTURE_ONLY'
            || ($receipt['archive_sha256'] ?? null) !== $plan['archive_sha256']
            || ($receipt['single_use'] ?? null) !== true
            || ($receipt['live_install_authorized'] ?? null) !== false) {
            throw new RuntimeException('AUTHORIZED_WORKER_CONSENT_INVALID');
        }
        $result = OneClickSignedFixtureBridgeV1::rehearse(
            $site, $vault, $signedEnvelopePath, $archivePath,
            $installedVersion, $crashAfter
        );
        if (($result['status'] ?? '') !== 'SIGNED_ISOLATED_FIXTURE_INSTALL_COMMITTED'
            || ($result['live_installation'] ?? null) !== false
            || ($result['database_writes'] ?? null) !== false) {
            throw new RuntimeException('AUTHORIZED_WORKER_FILE_APPLY_FAILED');
        }
        return [
            'status' => 'SIGNED_AND_OWNER_APPROVED_FIXTURE_INSTALLED',
            'consent_digest' => $receipt['consent_digest'],
            'archive_sha256' => $plan['archive_sha256'],
            'from_version' => $installedVersion,
            'to_version' => $plan['target_version'],
            'files_applied' => $result['files'],
            'transaction_id' => $result['transaction_id'],
            'database_writes' => false,
            'live_installation' => false,
        ];
    }
}
