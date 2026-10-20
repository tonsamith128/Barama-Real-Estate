<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;
use Throwable;

/**
 * Independently signed operator authorization for ONE exact signed release
 * AND ONE exact verified recovery checkpoint. Root-only staging consumption.
 *
 * NOT a privileged installer. No website file/SQL writes or root-web bridge.
 * A valid approval cannot enable deployment unless the separate privileged
 * worker independently validates all backup/restore prerequisites.
 *
 * Public key must be provisioned OUTSIDE source/GitHub into the root-private
 * owner-trust.json file. Never generate a keypair on the customer server.
 */
final class OneClickStagingOwnerApprovalV1
{
    private const SITE = '/www/wwwroot/baramastaging.tonsamith.com';
    private const VAULT = '/root/Barama-OneClick-Recovery/owner-approvals';
    private const FIXTURE = '~^/tmp/barama-staging-owner-fixture-[a-f0-9]{16}$~D';
    private const DOMAIN = "BARAMA-STAGING-ONECLICK-OWNER-V1\n";
    private const SHA = '/^[a-f0-9]{64}$/D';
    private const NONCE = '/^[a-f0-9]{32}$/D';
    private const VERSION = '/^\d{1,4}\.\d{1,4}\.\d{1,4}(?:-[A-Za-z0-9.]{1,24})?$/D';
    private const FIELDS = [
        'action', 'archive_sha256', 'database_name_sha256', 'expires_at',
        'issued_at', 'nonce', 'recovery_checkpoint_sha256', 'schema', 'site',
        'source_version', 'target_version',
    ];

    /**
     * @param array<string,mixed> $envelope Canonical owner-signed fields.
     * @param array<string,mixed> $expected Independent root-worker observations;
     *   NEVER trust a web POST or signed payload as backup verification.
     * @return array<string,mixed>
     */
    public static function verifyAndConsume(
        string $site, string $vault, array $envelope,
        array $expected, int $now
    ): array {
        $fixture = self::guard($site, $vault);
        if (!extension_loaded('sodium')) {
            throw new RuntimeException('STAGING_APPROVAL_SODIUM_REQUIRED');
        }
        $required = ['archive_sha256', 'database_name_sha256',
                     'recovery_checkpoint_sha256', 'source_version', 'target_version'];
        if (array_keys($expected) !== $required
            || !self::sha($expected['archive_sha256'])
            || !self::sha($expected['database_name_sha256'])
            || !self::sha($expected['recovery_checkpoint_sha256'])
            || !self::version($expected['source_version'])
            || !self::version($expected['target_version'])
            || version_compare($expected['target_version'], $expected['source_version'], '<=')) {
            throw new RuntimeException('STAGING_APPROVAL_OBSERVATION_UNTRUSTED');
        }
        if (array_keys($envelope) !== ['consent', 'signature']) {
            throw new RuntimeException('STAGING_APPROVAL_ENVELOPE_INVALID');
        }
        $body = $envelope['consent'];
        $sig = $envelope['signature'];
        if (!is_array($body) || !is_array($sig)
            || count($body) !== count(self::FIELDS)
            || !self::sameFields(array_keys($body), self::FIELDS)
            || array_keys($sig) !== ['algorithm', 'key_id', 'value']
            || $sig['algorithm'] !== 'Ed25519'
            || !is_string($sig['key_id'] ?? null)
            || !is_string($sig['value'] ?? null)
            || $body['schema'] !== 'barama-staging-oneclick-owner-consent-v1'
            || $body['action'] !== ($fixture
                ? 'approve_one_disposable_staging_fixture'
                : 'approve_one_signed_staging_upgrade')
            || $body['site'] !== $site
            || !is_string($body['nonce'])
            || preg_match(self::NONCE, $body['nonce']) !== 1
            || !is_int($body['issued_at']) || !is_int($body['expires_at'])
            || $body['issued_at'] < $now - 300
            || $body['issued_at'] > $now + 30
            || $body['expires_at'] <= $now
            || $body['expires_at'] > $body['issued_at'] + 300) {
            throw new RuntimeException('STAGING_APPROVAL_SCOPE_INVALID');
        }
        foreach ($required as $name) {
            if (!is_string($body[$name] ?? null)
                || !hash_equals($expected[$name], $body[$name])) {
                throw new RuntimeException('STAGING_APPROVAL_RELEASE_OR_BACKUP_MISMATCH');
            }
        }
        $keyFile = $vault . '/owner-trust.json';
        $key = self::privateFile($keyFile, 2048);
        try {
            $catalog = json_decode($key, true, 6, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('STAGING_APPROVAL_KEY_INVALID');
        }
        if (!is_array($catalog)
            || array_keys($catalog) !== ['schema', 'key_id', 'public_key']
            || $catalog['schema'] !== 'barama-staging-owner-trust-v1'
            || !is_string($catalog['key_id'])
            || preg_match('/^[a-z0-9-]{3,40}$/D', $catalog['key_id']) !== 1
            || !is_string($catalog['public_key'])) {
            throw new RuntimeException('STAGING_APPROVAL_KEY_INVALID');
        }
        $pk = base64_decode($catalog['public_key'], true);
        $signature = base64_decode($sig['value'], true);
        if ($sig['key_id'] !== $catalog['key_id']
            || !is_string($pk) || strlen($pk) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || !is_string($signature)
            || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            throw new RuntimeException('STAGING_APPROVAL_SIGNATURE_INVALID');
        }
        $canonical = $body;
        ksort($canonical, SORT_STRING);
        $message = self::DOMAIN . json_encode(
            $canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        if (!sodium_crypto_sign_verify_detached($signature, $message, $pk)) {
            throw new RuntimeException('STAGING_APPROVAL_SIGNATURE_INVALID');
        }
        $ledger = $vault . '/used-nonces';
        self::privateDir($ledger);
        $lockName = $vault . '/approval.lock';
        clearstatcache(true, $lockName);
        if (is_link($lockName) || (file_exists($lockName)
            && (!is_file($lockName) || (fileperms($lockName) & 0077) !== 0))) {
            throw new RuntimeException('STAGING_APPROVAL_LOCK_UNTRUSTED');
        }
        $lock = fopen($lockName, 'c+b');
        if (!is_resource($lock)) throw new RuntimeException('STAGING_APPROVAL_LOCK_UNAVAILABLE');
        try {
            if (!chmod($lockName, 0600) || !flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('STAGING_APPROVAL_LOCK_UNAVAILABLE');
            }
            $out = $ledger . '/nonce-' . $body['nonce'] . '.json';
            if (is_link($out) || file_exists($out)) {
                throw new RuntimeException('STAGING_APPROVAL_REPLAY_DENIED');
            }
            $record = json_encode([
                'schema' => 'barama-staging-owner-consumption-v1',
                'nonce' => $body['nonce'],
                'site' => $site,
                'consent_digest' => hash('sha256', $message),
                'archive_sha256' => $expected['archive_sha256'],
                'recovery_checkpoint_sha256' => $expected['recovery_checkpoint_sha256'],
                'used_at' => $now,
                'status' => 'consumed_pending_independent_worker_gates',
                'install_enabled' => false,
                'database_writes' => false,
                'website_writes' => false,
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            $fh = fopen($out, 'xb');
            if (!is_resource($fh)) throw new RuntimeException('STAGING_APPROVAL_NONCE_CREATE_FAILED');
            try {
                if (!chmod($out, 0600) || fwrite($fh, $record) !== strlen($record)
                    || !fflush($fh) || (function_exists('fsync') && !fsync($fh))) {
                    throw new RuntimeException('STAGING_APPROVAL_NONCE_WRITE_FAILED');
                }
            } finally {
                fclose($fh);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return [
            'status' => 'OWNER_APPROVAL_CONSUMED_WORKER_GATES_REQUIRED',
            'single_use' => true,
            'consent_digest' => hash('sha256', $message),
            'archive_sha256' => $expected['archive_sha256'],
            'recovery_checkpoint_sha256' => $expected['recovery_checkpoint_sha256'],
            'install_enabled' => false,
            'website_writes' => false,
            'database_writes' => false,
        ];
    }

    private static function guard(string $site, string $vault): bool
    {
        if (PHP_SAPI !== 'cli') throw new RuntimeException('STAGING_APPROVAL_CLI_ONLY');
        $parent = dirname($site);
        $fixture = preg_match(self::FIXTURE, $parent) === 1
            && $site === $parent . '/site' && $vault === $parent . '/vault';
        if (!$fixture && ($site !== self::SITE || $vault !== self::VAULT
            || !function_exists('posix_geteuid') || posix_geteuid() !== 0)) {
            throw new RuntimeException('STAGING_APPROVAL_ROOT_ONLY');
        }
        if ($fixture) self::privateDir($parent);
        self::privateDir($site, false);
        self::privateDir($vault);
        return $fixture;
    }

    private static function privateDir(string $path, bool $private = true): void
    {
        clearstatcache(true, $path);
        if (is_link($path) || !is_dir($path) || realpath($path) !== $path
            || ($private && (fileperms($path) & 0077) !== 0)) {
            throw new RuntimeException('STAGING_APPROVAL_PRIVATE_DIRECTORY_REQUIRED');
        }
    }

    private static function privateFile(string $file, int $max): string
    {
        clearstatcache(true, $file);
        if (is_link($file) || !is_file($file)
            || (fileperms($file) & 0077) !== 0
            || filesize($file) < 10 || filesize($file) > $max) {
            throw new RuntimeException('STAGING_APPROVAL_KEY_INVALID');
        }
        $data = file_get_contents($file);
        if (!is_string($data)) throw new RuntimeException('STAGING_APPROVAL_KEY_INVALID');
        return $data;
    }

    private static function sha(mixed $value): bool
    {
        return is_string($value) && preg_match(self::SHA, $value) === 1;
    }

    private static function version(mixed $value): bool
    {
        return is_string($value) && preg_match(self::VERSION, $value) === 1;
    }

    private static function sameFields(array $given, array $expected): bool
    {
        sort($given, SORT_STRING);
        sort($expected, SORT_STRING);
        return $given === $expected;
    }

    private function __construct()
    {
    }
}
