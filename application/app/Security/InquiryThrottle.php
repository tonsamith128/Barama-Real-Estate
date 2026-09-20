<?php

declare(strict_types=1);

namespace Barama\Security;

use Barama\Core\Database;
use DateInterval;
use DateTimeImmutable;
use PDO;

final class InquiryThrottle
{
    public function allow(string $ip): bool
    {
        $key = hash('sha256', 'inquiry|' . trim($ip));
        $pdo = Database::connection();
        $statement = $pdo->prepare(
            'SELECT id, attempts, window_started_at, blocked_until
             FROM inquiry_rate_limits
             WHERE key_hash = :key_hash
             LIMIT 1'
        );
        $statement->execute(['key_hash' => $key]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        $now = new DateTimeImmutable();
        $windowMinutes = max(1, (int) config('domain.inquiry_window_minutes', 15));
        $maxAttempts = max(1, (int) config('domain.inquiry_max_attempts', 5));

        if (!is_array($row)) {
            $insert = $pdo->prepare(
                'INSERT INTO inquiry_rate_limits
                    (key_hash, attempts, window_started_at, blocked_until)
                 VALUES
                    (:key_hash, 1, :window_started_at, NULL)'
            );
            $insert->execute([
                'key_hash' => $key,
                'window_started_at' => $now->format('Y-m-d H:i:s'),
            ]);

            return true;
        }

        if (
            is_string($row['blocked_until'])
            && $row['blocked_until'] !== ''
            && new DateTimeImmutable($row['blocked_until']) > $now
        ) {
            return false;
        }

        $windowStart = new DateTimeImmutable((string) $row['window_started_at']);
        $windowEnd = $windowStart->add(new DateInterval("PT{$windowMinutes}M"));

        if ($windowEnd <= $now) {
            $reset = $pdo->prepare(
                'UPDATE inquiry_rate_limits
                 SET attempts = 1,
                     window_started_at = :window_started_at,
                     blocked_until = NULL
                 WHERE id = :id'
            );
            $reset->execute([
                'window_started_at' => $now->format('Y-m-d H:i:s'),
                'id' => (int) $row['id'],
            ]);

            return true;
        }

        $attempts = (int) $row['attempts'] + 1;
        $blockedUntil = $attempts > $maxAttempts
            ? $now->add(new DateInterval("PT{$windowMinutes}M"))->format('Y-m-d H:i:s')
            : null;

        $update = $pdo->prepare(
            'UPDATE inquiry_rate_limits
             SET attempts = :attempts,
                 blocked_until = :blocked_until
             WHERE id = :id'
        );
        $update->execute([
            'attempts' => $attempts,
            'blocked_until' => $blockedUntil,
            'id' => (int) $row['id'],
        ]);

        return $blockedUntil === null;
    }
}
