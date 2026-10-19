<?php

declare(strict_types=1);

namespace Barama\Services\Updates;

use RuntimeException;

/**
 * Staging-only, web-user-owned UNTRUSTED request inbox.
 *
 * Nothing placed in this inbox is authority to run shell commands, edit the
 * website, install a release, or update a database. The future privileged
 * worker must validate signed publisher entitlement and native state again.
 */
final class DashboardUpdateRequestV1
{
    private const STAGING = '/www/wwwroot/baramastaging.tonsamith.com';
    private const PARENT = '/var/lib/barama-oneclick';
    private const INBOX = '/var/lib/barama-oneclick/staging-inbox';
    private const VERSION = '/^\d{1,4}\.\d{1,4}\.\d{1,4}(?:-[A-Za-z0-9.]{1,24})?$/D';
    private const SHA256 = '/^[a-f0-9]{64}$/D';

    public static function inboxReady(): bool
    {
        return self::trustedInbox(self::PARENT, self::INBOX);
    }

    /** @return array{request_id:string,state:string} */
    public static function enqueue(array $review, string $site, string $currentVersion): array
    {
        if ($site !== self::STAGING || PHP_SAPI === 'cli'
            || ($review['state'] ?? null) !== 'CANDIDATE_VERIFIED_FOR_REVIEW'
            || ($review['candidate_reviewed'] ?? null) !== true
            || ($review['install_authorized'] ?? null) !== false
            || ($review['root_worker_invoked'] ?? null) !== false
        ) {
            throw new RuntimeException('STAGING_REVIEW_ONLY');
        }
        return self::store($review, $site, $currentVersion, self::PARENT, self::INBOX, time());
    }

    /**
     * Disposable CLI fixture only. The public application never accepts
     * a user-selected spool path, and this entrypoint is never a web route.
     * @return array{request_id:string,state:string}
     */
    public static function fixture(
        array $review,
        string $currentVersion,
        string $parent,
        string $inbox,
        int $now
    ): array {
        if (PHP_SAPI !== 'cli' || getenv('BARAMA_HANDOFF_TEST') !== '1'
            || preg_match('~^/tmp/barama-ticket-fixture-[a-f0-9]{12,32}$~D', $parent) !== 1
            || $inbox !== $parent . '/inbox'
        ) {
            throw new RuntimeException('FIXTURE_NOT_AUTHORIZED');
        }
        return self::store($review, self::STAGING, $currentVersion, $parent, $inbox, $now);
    }

    private static function trustedInbox(string $parent, string $inbox): bool
    {
        clearstatcache(true, $parent);
        clearstatcache(true, $inbox);
        if (is_link($parent) || !is_dir($parent)
            || is_link($inbox) || !is_dir($inbox)
            || realpath($parent) !== $parent
            || realpath($inbox) !== $inbox
            || dirname($inbox) !== $parent
        ) return false;
        $p = stat($parent);
        $i = stat($inbox);
        if (!is_array($p) || !is_array($i)) return false;
        $fixture = PHP_SAPI === 'cli' && getenv('BARAMA_HANDOFF_TEST') === '1'
            && preg_match('~^/tmp/barama-ticket-fixture-[a-f0-9]{12,32}$~D', $parent) === 1;
        if (!$fixture && ($p['uid'] !== 0 || ($p['mode'] & 0777) !== 0711)) return false;
        if (!$fixture && (string)$parent !== self::PARENT) return false;
        return function_exists('posix_geteuid')
            && (int)$i['uid'] === (int)posix_geteuid()
            && (($i['mode'] & 0777) === 0700)
            && ($i['mode'] & 0007) === 0;
    }

    /** @return array{request_id:string,state:string} */
    private static function store(
        array $review,
        string $site,
        string $currentVersion,
        string $parent,
        string $inbox,
        int $now
    ): array {
        if (!self::trustedInbox($parent, $inbox)) {
            throw new RuntimeException('INBOX_NOT_PROVISIONED');
        }
        $target = $review['candidate_version'] ?? null;
        $sha = $review['archive_sha256'] ?? null;
        if (!is_string($target) || !is_string($sha)
            || preg_match(self::VERSION, $currentVersion) !== 1
            || preg_match(self::VERSION, $target) !== 1
            || version_compare($target, $currentVersion, '<=')
            || preg_match(self::SHA256, $sha) !== 1
            || ($review['candidate_reviewed'] ?? null) !== true
            || ($review['install_authorized'] ?? null) !== false
            || ($review['install_executed'] ?? null) !== false
            || ($review['root_worker_invoked'] ?? null) !== false
        ) {
            throw new RuntimeException('REVIEW_CANDIDATE_INVALID');
        }
        $existing = 0;
        foreach (new \DirectoryIterator($inbox) as $entry) {
            if (!$entry->isDot() && ++$existing >= 32) {
                throw new RuntimeException('INBOX_CAPACITY_REACHED');
            }
        }
        $id = bin2hex(random_bytes(16));
        $ticket = [
            'schema' => 'barama-dashboard-review-ticket-v1',
            'request_id' => $id,
            'site' => $site,
            'from_version' => $currentVersion,
            'to_version' => $target,
            'archive_sha256' => $sha,
            'created_at' => $now,
            'expires_at' => $now + 120,
            'action' => 'review_only',
        ];
        $bytes = json_encode($ticket, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (!is_string($bytes) || strlen($bytes) > 800) throw new RuntimeException('TICKET_SIZE_REJECTED');
        $file = $inbox . '/' . $id . '.json';
        $old = umask(0077);
        try {
            $handle = @fopen($file, 'xb');
            if ($handle === false) throw new RuntimeException('INBOX_CREATE_REJECTED');
            try {
                $written = fwrite($handle, $bytes . "\n");
                if ($written !== strlen($bytes) + 1 || !fflush($handle)) {
                    throw new RuntimeException('INBOX_WRITE_FAILED');
                }
            } catch (\Throwable $failure) {
                fclose($handle);
                @unlink($file);
                throw $failure;
            }
            fclose($handle);
        } finally {
            umask($old);
        }
        if (!self::trustedInbox($parent, $inbox)) {
            throw new RuntimeException('INBOX_CHANGED');
        }
        return ['request_id' => $id, 'state' => 'QUEUED_FOR_MANUAL_REVIEW_ONLY'];
    }
}
