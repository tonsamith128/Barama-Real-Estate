<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;
use ZipArchive;

/**
 * Cryptographically signed V2 package -> atomic file transaction, fixture only.
 * No installed websites, SQL, root/web handoff, release downloads or migrations.
 * This exact bridge MUST NOT be used as a live installation worker.
 */
final class OneClickSignedFixtureBridgeV1
{
    private const FIXTURE = '~^/tmp/barama-oneclick-transaction-fixture-[a-f0-9]{12,32}$~D';
    private const MAX_FILES = 128;
    private const MAX_BYTES = 16777216;

    /**
     * @return array{status:string,installed_version:string,target_version:string,archive_sha256:string,files:int,transaction_id:string,live_installation:bool,database_writes:bool}
     */
    public static function rehearse(
        string $site, string $vault, string $envelopePath,
        string $archivePath, string $installedVersion, ?int $crashAfter = null
    ): array {
        self::fixtureRoots($site, $vault);
        if (!class_exists(ZipArchive::class) || !extension_loaded('sodium')) {
            throw new RuntimeException('SIGNED_BRIDGE_RUNTIME_MISSING');
        }
        $versionFile = $site . '/VERSION';
        if (is_link($versionFile) || !is_file($versionFile)
            || trim((string) file_get_contents($versionFile)) !== $installedVersion
            || !preg_match('/^\d{1,4}\.\d{1,4}\.\d{1,4}(?:-[A-Za-z0-9.]{1,24})?$/D', $installedVersion)) {
            throw new RuntimeException('SIGNED_BRIDGE_VERSION_MISMATCH');
        }
        if (is_link($envelopePath) || is_link($archivePath)
            || !is_file($envelopePath) || !is_file($archivePath)
            || realpath($envelopePath) !== $envelopePath
            || realpath($archivePath) !== $archivePath
            || !str_starts_with($envelopePath, $site . '/application/storage/app/updates/staged/')
            || !str_starts_with($archivePath, $site . '/application/storage/app/updates/staged/')) {
            throw new RuntimeException('SIGNED_BRIDGE_STAGE_UNTRUSTED');
        }

        // Independently verifies Ed25519 publisher signature, version, complete
        // archive SHA256, ZIP entry safety and every currently installed baseline.
        $plan = VerifiedReleaseFilePlanV2::inspect(
            $envelopePath, $archivePath, $installedVersion
        );
        if (($plan['status'] ?? '') !== 'verified_plan_only'
            || ($plan['installation_enabled'] ?? true) !== false
            || ($plan['installed_version'] ?? '') !== $installedVersion
            || !is_array($plan['targets'] ?? null)
            || count($plan['targets']) < 2
            || count($plan['targets']) > self::MAX_FILES
            || ($plan['files'] ?? -1) !== count($plan['targets'])
            || !is_int($plan['bytes'] ?? null) || $plan['bytes'] > self::MAX_BYTES) {
            throw new RuntimeException('SIGNED_BRIDGE_PLAN_UNSUPPORTED');
        }

        $archiveSha = (string) ($plan['archive_sha256'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/D', $archiveSha)
            || !hash_equals($archiveSha, (string) hash_file('sha256', $archivePath))) {
            throw new RuntimeException('SIGNED_BRIDGE_ARCHIVE_CHANGED');
        }
        $zip = new ZipArchive();
        if ($zip->open($archivePath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('SIGNED_BRIDGE_ZIP_UNAVAILABLE');
        }
        $bytes = 0;
        $files = [];
        $seen = [];
        try {
            foreach ($plan['targets'] as $target) {
                $name = $target['path'] ?? null;
                if (!is_string($name) || !is_int($target['bytes'] ?? null)
                    || ($target['bytes'] < 0) || $target['bytes'] > self::MAX_BYTES
                    || !is_string($target['archive_sha256'] ?? null)
                    || preg_match('/^[a-f0-9]{64}$/D', $target['archive_sha256']) !== 1
                    || isset($seen[strtolower($name)])) {
                    throw new RuntimeException('SIGNED_BRIDGE_TARGET_INVALID');
                }
                $seen[strtolower($name)] = true;
                $stream = $zip->getStream($name);
                if (!is_resource($stream)) {
                    throw new RuntimeException('SIGNED_BRIDGE_ENTRY_UNREADABLE');
                }
                $buffer = '';
                try {
                    while (!feof($stream)) {
                        $part = fread($stream, 65536);
                        if (!is_string($part) || ($part === '' && !feof($stream))) {
                            throw new RuntimeException('SIGNED_BRIDGE_ENTRY_UNREADABLE');
                        }
                        $buffer .= $part;
                        if (strlen($buffer) > $target['bytes'] || strlen($buffer) > self::MAX_BYTES) {
                            throw new RuntimeException('SIGNED_BRIDGE_ENTRY_SIZE_CHANGED');
                        }
                    }
                } finally {
                    fclose($stream);
                }
                if (strlen($buffer) !== $target['bytes']
                    || !hash_equals($target['archive_sha256'], hash('sha256', $buffer))) {
                    throw new RuntimeException('SIGNED_BRIDGE_ENTRY_TAMPERED');
                }
                $bytes += strlen($buffer);
                if ($bytes > self::MAX_BYTES) throw new RuntimeException('SIGNED_BRIDGE_CAP_EXCEEDED');
                $files[$name] = $buffer;
            }
        } finally {
            $zip->close();
        }

        // Re-check the entire archive after decompression, before ANY mutation.
        if (count($files) !== $plan['files'] || $bytes !== $plan['bytes']
            || !hash_equals($archiveSha, (string) hash_file('sha256', $archivePath))) {
            throw new RuntimeException('SIGNED_BRIDGE_ARCHIVE_CHANGED');
        }
        // Only this existing fixture engine can write; it independently
        // validates roots, baselines, release bytes, and journal recovery.
        $transaction = OneClickAtomicTransactionV1::apply(
            $site, $vault, $plan, $files, $crashAfter
        );
        if (($transaction['status'] ?? null) !== 'ISOLATED_FILE_INSTALL_COMMITTED'
            || ($transaction['live_installation'] ?? null) !== false
            || ($transaction['database_writes'] ?? null) !== false
            || !is_string($transaction['transaction_id'] ?? null)) {
            throw new RuntimeException('SIGNED_BRIDGE_TRANSACTION_UNTRUSTED');
        }
        return [
            'status' => 'SIGNED_ISOLATED_FIXTURE_INSTALL_COMMITTED',
            'installed_version' => $installedVersion,
            'target_version' => $plan['target_version'],
            'archive_sha256' => $archiveSha,
            'files' => count($files),
            'transaction_id' => $transaction['transaction_id'],
            'live_installation' => false,
            'database_writes' => false,
        ];
    }

    private static function fixtureRoots(string $site, string $vault): void
    {
        $parent = dirname($site);
        if (PHP_SAPI !== 'cli' || !preg_match(self::FIXTURE, $parent)
            || $site !== $parent . '/site' || $vault !== $parent . '/vault'
            || !defined('BASE_PATH') || BASE_PATH !== $site
            || !defined('APP_PATH') || APP_PATH !== $site . '/application'
            || is_link($parent) || is_link($site) || is_link($vault)
            || !is_dir($site) || !is_dir($vault)
            || realpath($parent) !== $parent || realpath($site) !== $site
            || realpath($vault) !== $vault
            || (fileperms($vault) & 0077) !== 0) {
            throw new RuntimeException('SIGNED_BRIDGE_FIXTURE_ONLY');
        }
    }

    private function __construct()
    {
    }
}
