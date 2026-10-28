<?php

declare(strict_types=1);

namespace Barama\Services;

use RuntimeException;

/**
 * BARAMA_DEMO_CUSTOMER_RELEASE_GATE_V115.
 * Independently restrict customer installation to the NEW publisher-signed
 * release containing a full Policy Center snapshot and a supported content schema.
 * This class cannot authorize any ZIP by itself: the controller MUST perform
 * fresh publisher catalog entitlement, SHA-256 and Ed25519 verification.
 */
final class DemoCustomerReleasePolicyV1
{
    public const FIRST_COMPLETE_DEMO_VERSION = '0.9.26';
    public const REQUIRED_PUBLIC_POLICIES = 20;

    /** @param array<string,mixed> $proof Publisher-verified archive inspection. */
    public static function assertInstallable(array $proof, string $installedAppVersion): void
    {
        if (($proof['signature_verified'] ?? false) !== true) {
            throw new RuntimeException('DEMO_CUSTOMER_SIGNATURE_REQUIRED');
        }
        $version = $proof['package_version'] ?? null;
        if (!is_string($version) ||
            preg_match('/^\d+\.\d+\.\d+$/D', $version) !== 1 ||
            version_compare($version, self::FIRST_COMPLETE_DEMO_VERSION, '<')) {
            throw new RuntimeException('DEMO_CUSTOMER_RELEASE_NOT_READY');
        }
        if (!is_string($installedAppVersion) ||
            preg_match('/^\d+\.\d+\.\d+(?:-[A-Za-z0-9.]+)?$/D', $installedAppVersion) !== 1) {
            throw new RuntimeException('DEMO_CUSTOMER_APP_VERSION_INVALID');
        }
        // Demo content has an independent, signed format contract. Application
        // release labels are NOT a content-schema version: a valid snapshot
        // must remain usable across compatible app upgrades without re-signing.
        // The older compatible_app_versions field is retained for audit but
        // does not veto an otherwise compatible importer/database schema.
        if (($proof['format'] ?? null) !== 'tonsamith-manifest-json' ||
            ($proof['manifest_schema_version'] ?? null) !== 1 ||
            ($proof['content_schema'] ?? null) !== 'barama-demo-content-v1') {
            throw new RuntimeException('DEMO_CUSTOMER_CONTENT_SCHEMA_UNSUPPORTED');
        }
        $compatible = $proof['compatible_app_versions'] ?? null;
        if ($compatible !== null) {
            if (!is_array($compatible) || count($compatible) < 1 ||
                count($compatible) > 20 || array_values($compatible) !== $compatible) {
                throw new RuntimeException('DEMO_CUSTOMER_LEGACY_VERSION_METADATA_INVALID');
            }
            foreach ($compatible as $value) {
                if (!is_string($value) ||
                    preg_match('/^\d+\.\d+\.\d+(?:-[A-Za-z0-9.]+)?$/D', $value) !== 1) {
                    throw new RuntimeException('DEMO_CUSTOMER_LEGACY_VERSION_METADATA_INVALID');
                }
            }
        }
        if (($proof['policy_records'] ?? null) !== self::REQUIRED_PUBLIC_POLICIES) {
            throw new RuntimeException('DEMO_CUSTOMER_PUBLIC_POLICIES_REQUIRED');
        }
        if ((int)($proof['record_count'] ?? 0) <= 0 ||
            (int)($proof['media_images'] ?? 0) <= 0) {
            throw new RuntimeException('DEMO_CUSTOMER_SIGNED_DATA_INCOMPLETE');
        }
    }

    public static function canInstall(array $proof, string $installedAppVersion): bool
    {
        try {
            self::assertInstallable($proof, $installedAppVersion);
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }
}
