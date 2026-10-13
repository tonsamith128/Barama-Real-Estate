<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Session;
use Barama\Repositories\SecurityFactorRepository;
use Barama\Repositories\UserRepository;
use Barama\Security\Totp;
use DomainException;
use InvalidArgumentException;
use RuntimeException;

final class SecurityFactorService
{
    private const CHANGE_GRANTS_KEY = 'security.change_grants';
    private const PASSCODE_FAILURES_KEY = 'security.passcode_failures';
    private const PASSCODE_LOCKED_UNTIL_KEY = 'security.passcode_locked_until';
    private const GRANT_TTL_SECONDS = 90;
    private const MFA_SETUP_AUTH_UNTIL_KEY = 'security.mfa_setup_authorized_until';
    private const MFA_SETUP_AUTH_USER_KEY = 'security.mfa_setup_authorized_user_id';
    private const MFA_SETUP_AUTH_TTL_SECONDS = 600;

    /** @return array<string,mixed> */
    public function viewModel(int $userId): array
    {
        $row = (new SecurityFactorRepository())->get($userId);
        $recoveryCount = count((new SecurityFactorRepository())->recoveryHashes($userId));
        $pending = trim((string) ($row['mfa_pending_secret_enc'] ?? '')) !== '';
        $setupAuthorized = $pending && $this->mfaSetupAuthorized($userId);

        return [
            'passcode_enabled' => !empty($row['change_passcode_enabled'])
                && trim((string) ($row['change_passcode_hash'] ?? '')) !== '',
            'mfa_enabled' => !empty($row['mfa_enabled'])
                && trim((string) ($row['mfa_secret_enc'] ?? '')) !== '',
            'mfa_pending' => $pending,
            'mfa_setup_authorized' => $setupAuthorized,
            'mfa_confirmed_at' => $row['mfa_confirmed_at'] ?? null,
            'mfa_recovery_remaining' => $recoveryCount,
            'mfa_setup' => $setupAuthorized ? $this->pendingMfaSetup($userId) : null,
        ];
    }

    public function passcodeEnabled(int $userId): bool
    {
        $row = (new SecurityFactorRepository())->get($userId);
        return !empty($row['change_passcode_enabled'])
            && trim((string) ($row['change_passcode_hash'] ?? '')) !== '';
    }

    public function mfaEnabled(int $userId): bool
    {
        $row = (new SecurityFactorRepository())->get($userId);
        return !empty($row['mfa_enabled'])
            && trim((string) ($row['mfa_secret_enc'] ?? '')) !== '';
    }

    public function mfaSetupPending(int $userId): bool
    {
        $row = (new SecurityFactorRepository())->get($userId);
        return empty($row['mfa_enabled'])
            && trim((string) ($row['mfa_pending_secret_enc'] ?? '')) !== '';
    }

    /** @param array<string,mixed> $input */
    public function setPasscode(int $userId, array $input): void
    {
        $this->assertCurrentPassword($userId, (string) ($input['current_password'] ?? ''));
        $repo = new SecurityFactorRepository();
        $row = $repo->get($userId);

        if (!empty($row['change_passcode_enabled'])) {
            $current = (string) ($input['current_passcode'] ?? '');
            if (!$this->verifyPasscodeRaw($row, $current)) {
                throw new DomainException('Current change passcode is incorrect.');
            }
        }

        $passcode = trim((string) ($input['new_passcode'] ?? ''));
        $confirmation = trim((string) ($input['new_passcode_confirmation'] ?? ''));
        $this->assertStrongPasscode($passcode, $confirmation);

        $hash = password_hash($passcode, PASSWORD_DEFAULT);
        if (!is_string($hash)) {
            throw new RuntimeException('The change passcode could not be secured.');
        }

        $repo->savePasscode($userId, $hash, true);
        $this->clearChangeGrants();
        (new SecurityAuditService())->record(
            !empty($row['change_passcode_enabled'])
                ? 'security.change_passcode_rotated'
                : 'security.change_passcode_enabled',
            $userId,
            $userId
        );
    }

    /** @param array<string,mixed> $input */
    public function disablePasscode(int $userId, array $input): void
    {
        $this->assertCurrentPassword($userId, (string) ($input['current_password'] ?? ''));
        $row = (new SecurityFactorRepository())->get($userId);

        if (empty($row['change_passcode_enabled'])) {
            return;
        }

        if (!$this->verifyPasscodeRaw($row, (string) ($input['current_passcode'] ?? ''))) {
            throw new DomainException('Current change passcode is incorrect.');
        }

        (new SecurityFactorRepository())->disablePasscode($userId);
        $this->clearChangeGrants();
        (new SecurityAuditService())->record(
            'security.change_passcode_disabled',
            $userId,
            $userId
        );
    }

    public function issueChangeGrant(int $userId, string $passcode): string
    {
        if (!$this->passcodeEnabled($userId)) {
            return '';
        }

        $lockedUntil = (int) Session::get(self::PASSCODE_LOCKED_UNTIL_KEY, 0);
        if ($lockedUntil > time()) {
            throw new DomainException('Change passcode is temporarily locked. Try again in a few minutes.');
        }

        $row = (new SecurityFactorRepository())->get($userId);
        if (!$this->verifyPasscodeRaw($row, $passcode)) {
            $failures = (int) Session::get(self::PASSCODE_FAILURES_KEY, 0) + 1;
            Session::put(self::PASSCODE_FAILURES_KEY, $failures);
            if ($failures >= 5) {
                Session::put(self::PASSCODE_LOCKED_UNTIL_KEY, time() + 300);
                Session::put(self::PASSCODE_FAILURES_KEY, 0);
            }
            (new SecurityAuditService())->record(
                'security.change_passcode_failed',
                $userId,
                $userId,
                ['locked' => $failures >= 5]
            );
            throw new DomainException('The 6-digit change passcode is incorrect.');
        }

        Session::put(self::PASSCODE_FAILURES_KEY, 0);
        Session::forget(self::PASSCODE_LOCKED_UNTIL_KEY);

        $grant = bin2hex(random_bytes(24));
        $grants = Session::get(self::CHANGE_GRANTS_KEY, []);
        $grants = is_array($grants) ? $grants : [];
        $grants = array_filter(
            $grants,
            static fn (mixed $item): bool => is_array($item)
                && (int) ($item['expires_at'] ?? 0) >= time()
        );
        $grants[$grant] = [
            'user_id' => $userId,
            'expires_at' => time() + self::GRANT_TTL_SECONDS,
        ];
        if (count($grants) > 8) {
            $grants = array_slice($grants, -8, null, true);
        }
        Session::put(self::CHANGE_GRANTS_KEY, $grants);

        return $grant;
    }

    public function consumeChangeGrant(int $userId, string $grant): bool
    {
        if (!$this->passcodeEnabled($userId)) {
            return true;
        }

        if (preg_match('/^[a-f0-9]{48}$/', $grant) !== 1) {
            return false;
        }

        $grants = Session::get(self::CHANGE_GRANTS_KEY, []);
        if (!is_array($grants) || !isset($grants[$grant]) || !is_array($grants[$grant])) {
            return false;
        }

        $record = $grants[$grant];
        unset($grants[$grant]);
        Session::put(self::CHANGE_GRANTS_KEY, $grants);

        return (int) ($record['user_id'] ?? 0) === $userId
            && (int) ($record['expires_at'] ?? 0) >= time();
    }

    public function beginMfaSetup(int $userId, string $currentPassword): void
    {
        $this->assertCurrentPassword($userId, $currentPassword);
        $repo = new SecurityFactorRepository();
        $row = $repo->get($userId);
        if (!empty($row['mfa_enabled'])) {
            throw new DomainException('Multi-factor authentication is already enabled.');
        }

        $pending = trim((string) ($row['mfa_pending_secret_enc'] ?? ''));
        if ($pending === '') {
            $secret = Totp::generateSecret();
            $repo->savePendingMfaSecret($userId, $this->encrypt($secret));
        }

        Session::put(self::MFA_SETUP_AUTH_USER_KEY, $userId);
        Session::put(
            self::MFA_SETUP_AUTH_UNTIL_KEY,
            time() + self::MFA_SETUP_AUTH_TTL_SECONDS
        );

        (new SecurityAuditService())->record(
            $pending === '' ? 'security.mfa_setup_started' : 'security.mfa_setup_resumed',
            $userId,
            $userId
        );
    }

    public function mfaSetupAuthorized(int $userId): bool
    {
        if (
            (int) Session::get(self::MFA_SETUP_AUTH_USER_KEY, 0) !== $userId
            || (int) Session::get(self::MFA_SETUP_AUTH_UNTIL_KEY, 0) < time()
        ) {
            Session::forget(self::MFA_SETUP_AUTH_USER_KEY);
            Session::forget(self::MFA_SETUP_AUTH_UNTIL_KEY);
            return false;
        }

        $row = (new SecurityFactorRepository())->get($userId);
        return empty($row['mfa_enabled'])
            && trim((string) ($row['mfa_pending_secret_enc'] ?? '')) !== '';
    }

    /** @return array{secret:string,otpauth_uri:string}|null */
    public function pendingMfaSetup(int $userId): ?array
    {
        if (!$this->mfaSetupAuthorized($userId)) {
            return null;
        }

        $row = (new SecurityFactorRepository())->get($userId);
        $encrypted = trim((string) ($row['mfa_pending_secret_enc'] ?? ''));
        if ($encrypted === '') {
            return null;
        }

        $secret = $this->decrypt($encrypted);
        if ($secret === '') {
            throw new RuntimeException('Pending MFA setup could not be decrypted.');
        }

        $user = (new UserRepository())->findById($userId);
        $account = strtolower((string) ($user['email'] ?? ('user-' . $userId)));
        $issuer = trim((string) config('app.name', 'Barama Real Estate'));

        return [
            'secret' => $secret,
            'otpauth_uri' => Totp::otpauthUri($secret, $account, $issuer),
        ];
    }

    /** @return array<int,string> */
    public function enableMfa(int $userId, string $code): array
    {
        if (!$this->mfaSetupAuthorized($userId)) {
            throw new DomainException('Confirm your identity again before enabling MFA.');
        }

        $row = (new SecurityFactorRepository())->get($userId);
        if (!empty($row['mfa_enabled'])) {
            throw new DomainException('Multi-factor authentication is already enabled.');
        }

        $encrypted = trim((string) ($row['mfa_pending_secret_enc'] ?? ''));
        $secret = $encrypted !== '' ? $this->decrypt($encrypted) : '';
        if ($secret === '') {
            throw new DomainException('Start MFA setup before enabling it.');
        }
        if (!Totp::verify($secret, $code)) {
            throw new DomainException('The authenticator code is incorrect or expired.');
        }

        $codes = $this->generateRecoveryCodes();
        $hashes = array_map(
            fn (string $recovery): string => $this->recoveryHash($userId, $recovery),
            $codes
        );

        (new SecurityFactorRepository())->enableMfa(
            $userId,
            $this->encrypt($secret),
            $hashes
        );

        $this->clearMfaSetupAuthorization();

        (new SecurityAuditService())->record(
            'security.mfa_enabled',
            $userId,
            $userId,
            ['recovery_codes' => count($codes)]
        );

        return $codes;
    }

    public function cancelMfaSetup(int $userId, string $currentPassword): void
    {
        $this->assertCurrentPassword($userId, $currentPassword);
        (new SecurityFactorRepository())->cancelPendingMfa($userId);
        $this->clearMfaSetupAuthorization();
        (new SecurityAuditService())->record(
            'security.mfa_setup_cancelled',
            $userId,
            $userId
        );
    }

    /** @param array<string,mixed> $input */
    public function disableMfa(int $userId, array $input): void
    {
        $this->assertCurrentPassword($userId, (string) ($input['current_password'] ?? ''));
        if (!$this->verifyMfaCode($userId, (string) ($input['mfa_code'] ?? ''), true)) {
            throw new DomainException('Enter a valid authenticator or recovery code.');
        }

        (new SecurityFactorRepository())->disableMfa($userId);
        $this->clearMfaSetupAuthorization();
        (new SecurityAuditService())->record(
            'security.mfa_disabled',
            $userId,
            $userId
        );
    }

    /** @param array<string,mixed> $input @return array<int,string> */
    public function regenerateRecoveryCodes(int $userId, array $input): array
    {
        $this->assertCurrentPassword($userId, (string) ($input['current_password'] ?? ''));
        if (!$this->verifyMfaCode($userId, (string) ($input['mfa_code'] ?? ''), false)) {
            throw new DomainException('Enter a valid authenticator code.');
        }

        $codes = $this->generateRecoveryCodes();
        $hashes = array_map(
            fn (string $recovery): string => $this->recoveryHash($userId, $recovery),
            $codes
        );
        (new SecurityFactorRepository())->saveRecoveryHashes($userId, $hashes);

        (new SecurityAuditService())->record(
            'security.mfa_recovery_regenerated',
            $userId,
            $userId,
            ['recovery_codes' => count($codes)]
        );

        return $codes;
    }

    public function verifyMfaCode(int $userId, string $code, bool $consumeRecovery = true): bool
    {
        $row = (new SecurityFactorRepository())->get($userId);
        if (empty($row['mfa_enabled'])) {
            return false;
        }

        $normalized = strtoupper(trim($code));
        if (preg_match('/^\d{6}$/', $normalized) === 1) {
            $secret = $this->decrypt((string) ($row['mfa_secret_enc'] ?? ''));
            return $secret !== '' && Totp::verify($secret, $normalized);
        }

        $normalizedRecovery = preg_replace('/[^A-Z0-9]/', '', $normalized) ?? '';
        if (strlen($normalizedRecovery) !== 16) {
            return false;
        }

        $repo = new SecurityFactorRepository();
        $hashes = $repo->recoveryHashes($userId);
        $candidate = $this->recoveryHash($userId, $normalizedRecovery);
        $index = array_search($candidate, $hashes, true);
        if ($index === false) {
            return false;
        }

        if ($consumeRecovery) {
            unset($hashes[$index]);
            $repo->saveRecoveryHashes($userId, array_values($hashes));
            (new SecurityAuditService())->record(
                'security.mfa_recovery_used',
                $userId,
                $userId,
                ['remaining' => count($hashes)]
            );
        }

        return true;
    }

    private function assertCurrentPassword(int $userId, string $password): void
    {
        $user = (new UserRepository())->findById($userId);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            throw new DomainException('Current password is incorrect.');
        }
    }

    /** @param array<string,mixed> $row */
    private function verifyPasscodeRaw(array $row, string $passcode): bool
    {
        $hash = (string) ($row['change_passcode_hash'] ?? '');
        return preg_match('/^\d{6}$/', trim($passcode)) === 1
            && $hash !== ''
            && password_verify(trim($passcode), $hash);
    }

    private function assertStrongPasscode(string $passcode, string $confirmation): void
    {
        if (preg_match('/^\d{6}$/', $passcode) !== 1) {
            throw new InvalidArgumentException('Change passcode must contain exactly 6 digits.');
        }
        if (!hash_equals($passcode, $confirmation)) {
            throw new InvalidArgumentException('Change passcode confirmation does not match.');
        }

        $weak = [
            '000000', '111111', '222222', '333333', '444444',
            '555555', '666666', '777777', '888888', '999999',
            '123456', '654321', '012345', '543210', '121212',
        ];
        if (in_array($passcode, $weak, true)) {
            throw new InvalidArgumentException('Choose a less predictable 6-digit passcode.');
        }
    }

    /** @return array<int,string> */
    private function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $hex = strtoupper(bin2hex(random_bytes(8)));
            $codes[] = implode('-', str_split($hex, 4));
        }
        return $codes;
    }

    private function recoveryHash(int $userId, string $code): string
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', $code));
        return hash('sha256', $userId . ':' . $normalized);
    }

    private function encryptionKey(): string
    {
        $appKey = trim((string) env('APP_KEY', ''));
        if ($appKey === '') {
            throw new RuntimeException('APP_KEY is required before MFA can be configured.');
        }
        $decoded = base64_decode($appKey, true);
        $material = is_string($decoded) && $decoded !== '' ? $decoded : $appKey;
        return hash('sha256', $material, true);
    }

    private function encrypt(string $value): string
    {
        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('OpenSSL is required to protect MFA credentials.');
        }
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $value,
            'aes-256-gcm',
            $this->encryptionKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            'barama.security.mfa',
            16
        );
        if (!is_string($ciphertext) || strlen($tag) !== 16) {
            throw new RuntimeException('MFA credential encryption failed.');
        }
        return 'v1:' . base64_encode($iv . $tag . $ciphertext);
    }

    private function decrypt(string $value): string
    {
        if (!str_starts_with($value, 'v1:') || !function_exists('openssl_decrypt')) {
            return '';
        }
        $payload = base64_decode(substr($value, 3), true);
        if (!is_string($payload) || strlen($payload) < 29) {
            return '';
        }
        $plain = openssl_decrypt(
            substr($payload, 28),
            'aes-256-gcm',
            $this->encryptionKey(),
            OPENSSL_RAW_DATA,
            substr($payload, 0, 12),
            substr($payload, 12, 16),
            'barama.security.mfa'
        );
        return is_string($plain) ? $plain : '';
    }

    private function clearMfaSetupAuthorization(): void
    {
        Session::forget(self::MFA_SETUP_AUTH_USER_KEY);
        Session::forget(self::MFA_SETUP_AUTH_UNTIL_KEY);
    }

    private function clearChangeGrants(): void
    {
        Session::forget(self::CHANGE_GRANTS_KEY);
    }
}
