<?php

declare(strict_types=1);

namespace Barama\Security;

use Barama\Core\Database;
use DateInterval;
use DateTimeImmutable;
use PDO;

final class LoginThrottle
{
    public function isLocked(string $email, string $ip): bool
    {
        $key = $this->key($email, $ip);
        $statement = Database::connection()->prepare(
            'SELECT locked_until
             FROM login_attempts
             WHERE key_hash = :key_hash
             LIMIT 1'
        );
        $statement->execute(['key_hash' => $key]);

        $lockedUntil = $statement->fetchColumn();

        if (!is_string($lockedUntil) || $lockedUntil === '') {
            return false;
        }

        return new DateTimeImmutable($lockedUntil) > new DateTimeImmutable();
    }

    public function hit(string $email, string $ip): void
    {
        $pdo = Database::connection();
        $key = $this->key($email, $ip);
        $maxAttempts = (int) config('auth.login_max_attempts', 5);
        $decayMinutes = (int) config('auth.login_decay_minutes', 15);
        $now = new DateTimeImmutable();

        $select = $pdo->prepare(
            'SELECT id, attempts, first_attempt_at, locked_until
             FROM login_attempts
             WHERE key_hash = :key_hash
             LIMIT 1'
        );
        $select->execute(['key_hash' => $key]);

        $record = $select->fetch(PDO::FETCH_ASSOC);

        if (!is_array($record)) {
            $insert = $pdo->prepare(
                'INSERT INTO login_attempts
                    (key_hash, attempts, first_attempt_at, last_attempt_at, locked_until)
                 VALUES
                    (:key_hash, 1, :first_attempt_at, :last_attempt_at, NULL)'
            );
            $insert->execute([
                'key_hash' => $key,
                'first_attempt_at' => $now->format('Y-m-d H:i:s'),
                'last_attempt_at' => $now->format('Y-m-d H:i:s'),
            ]);

            return;
        }

        $firstAttemptAt = new DateTimeImmutable((string) $record['first_attempt_at']);
        $windowEnd = $firstAttemptAt->add(new DateInterval("PT{$decayMinutes}M"));

        if ($windowEnd <= $now) {
            $reset = $pdo->prepare(
                'UPDATE login_attempts
                 SET attempts = 1,
                     first_attempt_at = :first_attempt_at,
                     last_attempt_at = :last_attempt_at,
                     locked_until = NULL
                 WHERE id = :id'
            );
            $reset->execute([
                'first_attempt_at' => $now->format('Y-m-d H:i:s'),
                'last_attempt_at' => $now->format('Y-m-d H:i:s'),
                'id' => (int) $record['id'],
            ]);

            return;
        }

        $attempts = (int) $record['attempts'] + 1;
        $lockedUntil = $attempts >= $maxAttempts
            ? $now->add(new DateInterval("PT{$decayMinutes}M"))->format('Y-m-d H:i:s')
            : null;

        $update = $pdo->prepare(
            'UPDATE login_attempts
             SET attempts = :attempts,
                 last_attempt_at = :last_attempt_at,
                 locked_until = :locked_until
             WHERE id = :id'
        );
        $update->execute([
            'attempts' => $attempts,
            'last_attempt_at' => $now->format('Y-m-d H:i:s'),
            'locked_until' => $lockedUntil,
            'id' => (int) $record['id'],
        ]);
    }

    public function clear(string $email, string $ip): void
    {
        $statement = Database::connection()->prepare(
            'DELETE FROM login_attempts WHERE key_hash = :key_hash'
        );
        $statement->execute(['key_hash' => $this->key($email, $ip)]);
    }

    private function key(string $email, string $ip): string
    {
        return hash('sha256', strtolower(trim($email)) . '|' . trim($ip));
    }
}
