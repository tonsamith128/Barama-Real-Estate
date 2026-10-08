<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use PDO;

final class SecurityFactorRepository
{
    /** @return array<string,mixed> */
    public function get(int $userId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT user_id, change_passcode_hash, change_passcode_enabled,
                    mfa_secret_enc, mfa_pending_secret_enc, mfa_enabled,
                    mfa_recovery_hashes, mfa_confirmed_at, created_at, updated_at
             FROM user_security_factors
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : $this->defaults($userId);
    }

    public function ensure(int $userId): void
    {
        Database::connection()->prepare(
            'INSERT INTO user_security_factors (user_id)
             VALUES (:user_id)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)'
        )->execute(['user_id' => $userId]);
    }

    public function savePasscode(int $userId, string $hash, bool $enabled = true): void
    {
        $this->ensure($userId);
        Database::connection()->prepare(
            'UPDATE user_security_factors
             SET change_passcode_hash = :hash,
                 change_passcode_enabled = :enabled,
                 updated_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id'
        )->execute([
            'hash' => $hash,
            'enabled' => $enabled ? 1 : 0,
            'user_id' => $userId,
        ]);
    }

    public function disablePasscode(int $userId): void
    {
        $this->ensure($userId);
        Database::connection()->prepare(
            'UPDATE user_security_factors
             SET change_passcode_hash = NULL,
                 change_passcode_enabled = 0,
                 updated_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id'
        )->execute(['user_id' => $userId]);
    }

    public function savePendingMfaSecret(int $userId, string $encrypted): void
    {
        $this->ensure($userId);
        Database::connection()->prepare(
            'UPDATE user_security_factors
             SET mfa_pending_secret_enc = :secret,
                 updated_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id'
        )->execute(['secret' => $encrypted, 'user_id' => $userId]);
    }

    /** @param array<int,string> $recoveryHashes */
    public function enableMfa(
        int $userId,
        string $encryptedSecret,
        array $recoveryHashes
    ): void {
        $this->ensure($userId);
        Database::connection()->prepare(
            'UPDATE user_security_factors
             SET mfa_secret_enc = :secret,
                 mfa_pending_secret_enc = NULL,
                 mfa_enabled = 1,
                 mfa_recovery_hashes = :recovery,
                 mfa_confirmed_at = CURRENT_TIMESTAMP,
                 updated_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id'
        )->execute([
            'secret' => $encryptedSecret,
            'recovery' => json_encode(array_values($recoveryHashes), JSON_THROW_ON_ERROR),
            'user_id' => $userId,
        ]);
    }

    /** @param array<int,string> $recoveryHashes */
    public function saveRecoveryHashes(int $userId, array $recoveryHashes): void
    {
        $this->ensure($userId);
        Database::connection()->prepare(
            'UPDATE user_security_factors
             SET mfa_recovery_hashes = :recovery,
                 updated_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id'
        )->execute([
            'recovery' => json_encode(array_values($recoveryHashes), JSON_THROW_ON_ERROR),
            'user_id' => $userId,
        ]);
    }

    public function cancelPendingMfa(int $userId): void
    {
        $this->ensure($userId);
        Database::connection()->prepare(
            'UPDATE user_security_factors
             SET mfa_pending_secret_enc = NULL,
                 updated_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id'
        )->execute(['user_id' => $userId]);
    }

    public function disableMfa(int $userId): void
    {
        $this->ensure($userId);
        Database::connection()->prepare(
            'UPDATE user_security_factors
             SET mfa_secret_enc = NULL,
                 mfa_pending_secret_enc = NULL,
                 mfa_enabled = 0,
                 mfa_recovery_hashes = NULL,
                 mfa_confirmed_at = NULL,
                 updated_at = CURRENT_TIMESTAMP
             WHERE user_id = :user_id'
        )->execute(['user_id' => $userId]);
    }

    /** @return array<int,string> */
    public function recoveryHashes(int $userId): array
    {
        $json = (string) ($this->get($userId)['mfa_recovery_hashes'] ?? '');
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_filter(
            array_map(static fn (mixed $value): string => (string) $value, $decoded),
            static fn (string $value): bool => preg_match('/^[a-f0-9]{64}$/', $value) === 1
        ));
    }

    /** @return array<string,mixed> */
    private function defaults(int $userId): array
    {
        return [
            'user_id' => $userId,
            'change_passcode_hash' => null,
            'change_passcode_enabled' => 0,
            'mfa_secret_enc' => null,
            'mfa_pending_secret_enc' => null,
            'mfa_enabled' => 0,
            'mfa_recovery_hashes' => null,
            'mfa_confirmed_at' => null,
            'created_at' => null,
            'updated_at' => null,
        ];
    }
}
