<?php

declare(strict_types=1);

namespace Barama\Services;

use RuntimeException;

/**
 * Installation-wide quota for administrator mail transport tests.
 *
 * Even when a public demo account has administrator permissions, this
 * prevents the custom recipient field becoming an unrestricted relay.
 * The private state file contains timestamps only, never email addresses.
 */
final class MailTestLimiter
{
    private const MAX_PER_HOUR = 3;
    private const MAX_PER_DAY = 10;

    public function __construct(private readonly ?string $stateFile = null)
    {
    }

    public function consume(?int $now = null): void
    {
        $now ??= time();
        $path = $this->stateFile ?? APP_PATH . '/storage/app/mail/test-send-quota.json';
        $directory = dirname($path);

        if (is_link($directory) || (file_exists($path) && is_link($path))) {
            throw new RuntimeException('Test email rate-limit storage is unsafe.');
        }
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Test email rate-limit storage is not available.');
        }

        $handle = @fopen($path, 'c+');
        if (!is_resource($handle)) {
            throw new RuntimeException('Test email rate-limit storage is not writable.');
        }

        try {
            @chmod($path, 0600);
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Test email rate-limit lock failed.');
            }

            $raw = stream_get_contents($handle);
            if (!is_string($raw) || strlen($raw) > 65536) {
                throw new RuntimeException('Test email rate-limit state is invalid.');
            }
            $decoded = $raw === '' ? [] : json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new RuntimeException('Test email rate-limit state is invalid.');
            }

            $recent = [];
            foreach ($decoded as $timestamp) {
                if (!is_int($timestamp) || $timestamp > $now + 60) {
                    throw new RuntimeException('Test email rate-limit state is invalid.');
                }
                if ($timestamp > $now - 86400) {
                    $recent[] = $timestamp;
                }
            }

            $hourly = count(array_filter(
                $recent,
                static fn (int $timestamp): bool => $timestamp > $now - 3600
            ));
            if ($hourly >= self::MAX_PER_HOUR || count($recent) >= self::MAX_PER_DAY) {
                throw new RuntimeException(
                    'Test email limit reached (3 per hour, 10 per day across the installation). '
                    . 'Wait before sending another test.'
                );
            }

            $recent[] = $now;
            $encoded = json_encode($recent, JSON_THROW_ON_ERROR);
            rewind($handle);
            if (!ftruncate($handle, 0)
                || fwrite($handle, $encoded) !== strlen($encoded)
                || !fflush($handle)) {
                throw new RuntimeException('Test email rate-limit state could not be saved.');
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
