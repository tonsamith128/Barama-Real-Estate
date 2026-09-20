<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\AccountSettingsRepository;
use Barama\Repositories\UserRepository;
use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class AccountSettingsService
{
    /** @param array<string,mixed> $input */
    public function updateProfile(int $userId, array $input): void
    {
        $name = trim((string) ($input['name'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        $timezone = trim((string) ($input['timezone'] ?? 'UTC'));
        $language = strtolower(trim((string) ($input['preferred_language'] ?? 'en')));

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Name must be between 2 and 120 characters.');
        }

        if (mb_strlen($phone) > 60) {
            throw new InvalidArgumentException('Phone number is too long.');
        }

        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException('Selected timezone is invalid.');
        }

        if (preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $language) !== 1) {
            throw new InvalidArgumentException('Preferred language is invalid.');
        }

        (new AccountSettingsRepository())->saveProfile(
            $userId,
            $name,
            $phone !== '' ? $phone : null,
            $timezone,
            $language
        );
    }

    /** @param array<string,mixed> $input */
    public function updatePrivacy(int $userId, array $input): void
    {
        $remember = in_array(
            (string) ($input['remember_recent_views'] ?? ''),
            ['1', 'true', 'on'],
            true
        );

        (new AccountSettingsRepository())->savePrivacy($userId, $remember);

        if (!$remember) {
            (new AccountSettingsRepository())->clearRecentViews($userId);
        }
    }

    public function clearRecentViews(int $userId): void
    {
        (new AccountSettingsRepository())->clearRecentViews($userId);
    }

    /** @param array<string,mixed> $input */
    public function changePassword(int $userId, array $input): void
    {
        $user = (new UserRepository())->findById($userId);

        if ($user === null || !password_verify(
            (string) ($input['current_password'] ?? ''),
            (string) $user['password_hash']
        )) {
            throw new DomainException('Current password is incorrect.');
        }

        $password = (string) ($input['password'] ?? '');
        $confirmation = (string) ($input['password_confirmation'] ?? '');

        $this->assertStrongPassword($password, $confirmation);

        if (password_verify($password, (string) $user['password_hash'])) {
            throw new InvalidArgumentException('New password must be different from the current password.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        if (!is_string($hash)) {
            throw new RuntimeException('The new password could not be secured.');
        }

        (new UserRepository())->updatePassword($userId, $hash);
    }

    /** @param array<string,mixed> $input */
    public function requestEmailChange(int $userId, array $input): void
    {
        $users = new UserRepository();
        $user = $users->findById($userId);

        if ($user === null || !password_verify(
            (string) ($input['current_password'] ?? ''),
            (string) $user['password_hash']
        )) {
            throw new DomainException('Current password is incorrect.');
        }

        $newEmail = strtolower(trim((string) ($input['new_email'] ?? '')));

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid new email address.');
        }

        if ($newEmail === strtolower((string) $user['email'])) {
            throw new InvalidArgumentException('New email address must be different from the current address.');
        }

        if ($users->findByEmail($newEmail) !== null) {
            throw new InvalidArgumentException('That email address is already in use.');
        }

        $token = bin2hex(random_bytes(32));
        $repository = new AccountSettingsRepository();
        $repository->createEmailChangeToken(
            $userId,
            $newEmail,
            hash('sha256', $token),
            date('Y-m-d H:i:s', time() + 3600)
        );

        $confirmationUrl = url('/account/email-change/' . rawurlencode($token));

        (new NotificationService())->notifyAddress($newEmail, [
            'subject' => 'Confirm your Barama email address',
            'html' => '<p><strong>Confirm your new email address</strong></p>'
                . '<p>Open the secure confirmation page while signed in to your Barama account.</p>'
                . '<p><a href="' . e($confirmationUrl) . '">Confirm email address</a></p>'
                . '<p>This link expires in 60 minutes.</p>',
            'text' => "Confirm your new Barama email address:\n\n"
                . $confirmationUrl . "\n\nThis link expires in 60 minutes.",
            'dedupe_key' => 'email-change:' . $userId . ':' . hash('sha256', $newEmail . $token),
        ]);
    }

    public function emailChangePreview(int $userId, string $token): ?string
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            return null;
        }

        $record = (new AccountSettingsRepository())->validEmailChangeToken(
            hash('sha256', $token),
            $userId
        );

        return $record !== null ? (string) $record['new_email'] : null;
    }

    public function confirmEmailChange(int $userId, string $token): void
    {
        $repository = new AccountSettingsRepository();
        $record = $repository->validEmailChangeToken(hash('sha256', $token), $userId);

        if ($record === null) {
            throw new InvalidArgumentException('This email confirmation link is invalid or expired.');
        }

        if ((new UserRepository())->findByEmail((string) $record['new_email']) !== null) {
            throw new InvalidArgumentException('That email address is no longer available.');
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();
            $repository->updateUserEmail($userId, (string) $record['new_email']);
            $repository->markEmailChangeUsed((int) $record['id']);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string,mixed> $input */
    public function deactivateAccount(int $userId, array $input): void
    {
        $user = (new UserRepository())->findById($userId);

        if ($user === null || !password_verify(
            (string) ($input['current_password'] ?? ''),
            (string) $user['password_hash']
        )) {
            throw new DomainException('Current password is incorrect.');
        }

        $repository = new AccountSettingsRepository();

        if ($repository->activeOwnerCompanyCount($userId) > 0) {
            throw new DomainException(
                'Transfer or archive company ownership before deactivating this account.'
            );
        }

        $repository->setUserStatus($userId, 'deactivated');
        (new AuthService())->logout();
    }

    private function assertStrongPassword(string $password, string $confirmation): void
    {
        if ($password !== $confirmation) {
            throw new InvalidArgumentException('Password confirmation does not match.');
        }

        if (
            strlen($password) < 12
            || preg_match('/[A-Z]/', $password) !== 1
            || preg_match('/[a-z]/', $password) !== 1
            || preg_match('/\d/', $password) !== 1
            || preg_match('/[^A-Za-z0-9]/', $password) !== 1
        ) {
            throw new InvalidArgumentException(
                'Use at least 12 characters with uppercase, lowercase, number, and symbol.'
            );
        }
    }
}
