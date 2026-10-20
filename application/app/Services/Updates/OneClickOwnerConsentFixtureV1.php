<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;

/**
 * Independent Ed25519 operator consent with durable single-use nonce.
 * HARD-FIXTURE ONLY: it cannot authorize an update to any real website.
 * Customer deployments need a separately audited root-owned trust bootstrap,
 * approval UX, recovery transaction, and DB coordination before reuse.
 */
final class OneClickOwnerConsentFixtureV1
{
    private const ROOT = '~^/tmp/barama-oneclick-(?:consent|transaction)-fixture-[a-f0-9]{16}$~D';
    private const DOMAIN = "BARAMA-OWNER-ONECLICK-CONSENT-V1\n";
    private const SHA = '/^[a-f0-9]{64}$/D';
    private const NONCE = '/^[a-f0-9]{32}$/D';
    private const VERSION = '/^\d{1,4}\.\d{1,4}\.\d{1,4}(?:-[A-Za-z0-9.]{1,24})?$/D';
    private const FIELDS = [
        'action', 'archive_sha256', 'expires_at', 'issued_at', 'nonce',
        'schema', 'site', 'source_version', 'target_version',
    ];

    /**
     * @param array<string,mixed> $envelope Untrusted input, never shell or SQL.
     * @return array<string,mixed> Consent recording is NOT live install authority.
     */
    public static function consumeFixture(
        string $site, string $vault, array $envelope,
        string $expectedArchiveSha256, string $installedVersion,
        string $targetVersion, int $now
    ): array {
        self::guard($site, $vault);
        if (!extension_loaded('sodium')) throw new RuntimeException('CONSENT_SODIUM_REQUIRED');
        $pubPath = $vault . '/owner-public-key.json';
        $key = self::readPrivateFile($pubPath, 2048);
        $catalog = json_decode($key, true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($catalog) || array_keys($catalog) !== ['schema', 'key_id', 'public_key']
            || $catalog['schema'] !== 'barama-fixture-owner-key-v1'
            || !is_string($catalog['key_id']) || !preg_match('/^[a-z0-9-]{3,40}$/D', $catalog['key_id'])
            || !is_string($catalog['public_key'])) {
            throw new RuntimeException('CONSENT_TRUST_CATALOG_INVALID');
        }
        $public = base64_decode($catalog['public_key'], true);
        if (!is_string($public) || strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new RuntimeException('CONSENT_TRUST_CATALOG_INVALID');
        }
        if (array_keys($envelope) !== ['consent', 'signature']) {
            throw new RuntimeException('CONSENT_ENVELOPE_INVALID');
        }
        $approval = $envelope['consent'];
        $sig = $envelope['signature'];
        if (!is_array($approval) || !is_array($sig)
            || count($approval) !== count(self::FIELDS)
            || !self::sameFields(array_keys($approval), self::FIELDS)
            || array_keys($sig) !== ['algorithm', 'key_id', 'value']
            || $sig['algorithm'] !== 'Ed25519'
            || $sig['key_id'] !== $catalog['key_id']
            || !is_string($sig['value'])) {
            throw new RuntimeException('CONSENT_ENVELOPE_INVALID');
        }
        if (($approval['schema'] ?? null) !== 'barama-owner-oneclick-consent-v1'
            || ($approval['action'] ?? null) !== 'approve_one_disposable_signed_update'
            || $approval['site'] !== $site
            || !self::sha($expectedArchiveSha256)
            || ($approval['archive_sha256'] ?? null) !== $expectedArchiveSha256
            || !is_string($installedVersion) || !is_string($targetVersion)
            || preg_match(self::VERSION, $installedVersion) !== 1
            || preg_match(self::VERSION, $targetVersion) !== 1
            || version_compare($targetVersion, $installedVersion, '<=')
            || ($approval['source_version'] ?? null) !== $installedVersion
            || ($approval['target_version'] ?? null) !== $targetVersion
            || !is_string($approval['nonce'] ?? null)
            || preg_match(self::NONCE, $approval['nonce']) !== 1
            || !is_int($approval['issued_at'] ?? null)
            || !is_int($approval['expires_at'] ?? null)
            || $approval['issued_at'] < $now - 300
            || $approval['issued_at'] > $now + 30
            || $approval['expires_at'] <= $now
            || $approval['expires_at'] > $approval['issued_at'] + 300) {
            throw new RuntimeException('CONSENT_SCOPE_OR_EXPIRY_INVALID');
        }
        $canonical = $approval;
        ksort($canonical, SORT_STRING);
        $encoded = json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $signature = base64_decode($sig['value'], true);
        if (!is_string($signature) || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
            || !sodium_crypto_sign_verify_detached($signature, self::DOMAIN . $encoded, $public)) {
            throw new RuntimeException('CONSENT_SIGNATURE_INVALID');
        }

        // Serialize single-use nonce consumption across concurrent processes.
        $lockPath = $vault . '/consent.lock';
        if (is_link($lockPath)) throw new RuntimeException('CONSENT_LOCK_UNTRUSTED');
        $lock = fopen($lockPath, 'c+b');
        if (!is_resource($lock)) throw new RuntimeException('CONSENT_LOCK_UNAVAILABLE');
        try {
            if (!chmod($lockPath, 0600) || !flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('CONSENT_LOCK_UNAVAILABLE');
            }
            $ledger = $vault . '/used-nonces';
            self::dir($ledger);
            $recordPath = $ledger . '/nonce-' . $approval['nonce'] . '.json';
            if (is_link($recordPath) || file_exists($recordPath)) {
                throw new RuntimeException('CONSENT_REPLAY_DENIED');
            }
            $audit = json_encode([
                'schema' => 'barama-oneclick-fixture-consent-record-v1',
                'nonce' => $approval['nonce'],
                'approved_archive_sha256' => $expectedArchiveSha256,
                'site' => $site,
                'used_at' => $now,
                'consent_digest' => hash('sha256', self::DOMAIN . $encoded),
                'status' => 'consumed_for_disposable_qa_only',
                'live_install_authorized' => false,
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            $handle = fopen($recordPath, 'xb');
            if (!is_resource($handle)) throw new RuntimeException('CONSENT_NONCE_COMMIT_FAILED');
            try {
                if (!chmod($recordPath, 0600)
                    || fwrite($handle, $audit) !== strlen($audit)
                    || !fflush($handle) || (function_exists('fsync') && !fsync($handle))) {
                    throw new RuntimeException('CONSENT_NONCE_COMMIT_FAILED');
                }
            } finally {
                fclose($handle);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return [
            'status' => 'INDEPENDENT_CONSENT_RECORDED_FIXTURE_ONLY',
            'consent_digest' => hash('sha256', self::DOMAIN . $encoded),
            'archive_sha256' => $expectedArchiveSha256,
            'single_use' => true,
            'live_install_authorized' => false,
            'database_writes' => false,
        ];
    }

    private static function guard(string $site, string $vault): void
    {
        $base = dirname($site);
        if (PHP_SAPI !== 'cli' || !preg_match(self::ROOT, $base)
            || $site !== $base . '/site' || $vault !== $base . '/vault'
            || is_link($base) || is_link($site) || is_link($vault)
            || !is_dir($site) || !is_dir($vault)
            || realpath($base) !== $base || realpath($site) !== $site
            || realpath($vault) !== $vault) {
            throw new RuntimeException('CONSENT_DISPOSABLE_FIXTURE_ONLY');
        }
        self::dir($vault);
    }

    private static function dir(string $path): void
    {
        if (is_link($path) || !is_dir($path) || realpath($path) !== $path
            || (fileperms($path) & 0077) !== 0) {
            throw new RuntimeException('CONSENT_PRIVATE_DIRECTORY_REQUIRED');
        }
    }

    private static function readPrivateFile(string $path, int $max): string
    {
        if (is_link($path) || !is_file($path) || (fileperms($path) & 0077) !== 0
            || filesize($path) < 10 || filesize($path) > $max) {
            throw new RuntimeException('CONSENT_TRUST_CATALOG_UNAVAILABLE');
        }
        $raw = file_get_contents($path);
        if (!is_string($raw)) throw new RuntimeException('CONSENT_TRUST_CATALOG_UNAVAILABLE');
        return $raw;
    }

    private static function sameFields(array $fields, array $expected): bool
    {
        sort($fields, SORT_STRING);
        sort($expected, SORT_STRING);
        return $fields === $expected;
    }

    private static function sha(mixed $s): bool
    {
        return is_string($s) && preg_match(self::SHA, $s) === 1;
    }
}
