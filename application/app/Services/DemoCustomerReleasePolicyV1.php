<?php

declare(strict_types=1);

namespace Barama\Services;

use RuntimeException;

/**
 * BARAMA_DEMO_CUSTOMER_RELEASE_GATE_V115.
 * Independently restrict customer installation to the NEW publisher-signed
 * release containing a full Policy Center snapshot and explicit app support.
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
        $compatible = $proof['compatible_app_versions'] ?? null;
        if (!is_array($compatible) || count($compatible) < 1 || count($compatible) > 20) {
            throw new RuntimeException('DEMO_CUSTOMER_APP_NOT_DECLARED_COMPATIBLE');
        }
        foreach ($compatible as $value) {
            if (!is_string($value) ||
                preg_match('/^\d+\.\d+\.\d+(?:-[A-Za-z0-9.]+)?$/D', $value) !== 1) {
                throw new RuntimeException('DEMO_CUSTOMER_APP_NOT_DECLARED_COMPATIBLE');
            }
        }
        if (!in_array($installedAppVersion, $compatible, true)) {
            throw new RuntimeException('DEMO_CUSTOMER_APP_NOT_DECLARED_COMPATIBLE');
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
