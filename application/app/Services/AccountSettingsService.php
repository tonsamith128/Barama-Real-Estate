<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Core\Session;
use Barama\Repositories\AccountSettingsRepository;
use Barama\Repositories\SecurityAuditRepository;
use Barama\Security\PasswordPolicy;
use Barama\Support\Locale;
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
        $dateOfBirth = trim((string) ($input['date_of_birth'] ?? ''));
        $address = trim((string) ($input['address'] ?? ''));
        $timezone = trim((string) ($input['timezone'] ?? 'UTC'));
        $language = Locale::normalize((string) ($input['preferred_language'] ?? Locale::current()));

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Name must be between 2 and 120 characters.');
        }

        if (mb_strlen($phone) > 60) {
            throw new InvalidArgumentException('Phone number is too long.');
        }

        if ($dateOfBirth !== '') {
            if (
                preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfBirth) !== 1
                || !checkdate(
                    (int) substr($dateOfBirth, 5, 2),
                    (int) substr($dateOfBirth, 8, 2),
                    (int) substr($dateOfBirth, 0, 4)
                )
                || $dateOfBirth < '1900-01-01'
                || $dateOfBirth > date('Y-m-d')
            ) {
                throw new InvalidArgumentException('Select a valid date of birth.');
            }
        }

        if (mb_strlen($address) > 500) {
            throw new InvalidArgumentException('Address must be 500 characters or fewer.');
        }

        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            throw new InvalidArgumentException('Selected timezone is invalid.');
        }

        if ($language === null) {
            throw new InvalidArgumentException('Preferred language is not supported.');
        }

        (new AccountSettingsRepository())->saveProfile(
            $userId,
            $name,
            $phone !== '' ? $phone : null,
            $dateOfBirth !== '' ? $dateOfBirth : null,
            $address !== '' ? $address : null,
            $timezone,
            $language
        );

        Locale::set($language);
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

    public function signOutOtherSessions(int $userId): void
    {
        $newVersion = (new UserRepository())->incrementAuthVersion($userId);

        (new AuthService())->refreshSessionVersion(
            $userId,
            $newVersion
        );

        (new SecurityAuditService())->record(
            'account.other_sessions_revoked',
            $userId,
            $userId
        );
    }

    /** @return array<string,int> */
    public function currentSessionInfo(): array
    {
        return (new AuthService())->currentSessionInfo();
    }

    /** @return array<int,array<string,string>> */
    public function recentSecurityActivity(
        int $userId,
        int $limit = 8
    ): array {
        $events = (new SecurityAuditRepository())->recentForUser(
            $userId,
            $limit
        );

        $labels = [
            'auth.login_success' => 'Successful sign-in',
            'auth.login_failed' => 'Failed sign-in attempt',
            'auth.login_locked' => 'Sign-in temporarily locked',
            'auth.logout' => 'Signed out',
            'account.password_changed' => 'Password changed',
            'account.email_change_requested' =>
                'Email change requested',
            'account.email_changed' => 'Email address changed',
            'account.other_sessions_revoked' =>
                'Other sessions signed out',
            'account.deactivated' => 'Account deactivated',
            'admin.storage_settings_updated' =>
                'Storage policy updated',
            'admin.storage_write_test' =>
                'Storage write test completed',
            'admin.storage_temp_cleanup' =>
                'Temporary storage cleaned',
        ];

        return array_map(
            static function (array $event) use ($labels): array {
                $type = (string) ($event['event_type'] ?? '');

                return [
                    'type' => $type,
                    'label' => $labels[$type]
                        ?? ucwords(
                            str_replace(
                                ['.', '_', '-'],
                                ' ',
                                $type
                            )
                        ),
                    'created_at' =>
                        (string) ($event['created_at'] ?? ''),
                ];
            },
            $events
        );
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

        $passwordErrors = PasswordPolicy::errors($password, $confirmation);

        if ($passwordErrors !== []) {
            throw new InvalidArgumentException(implode(' ', $passwordErrors));
        }

        if (password_verify($password, (string) $user['password_hash'])) {
            throw new InvalidArgumentException('New password must be different from the current password.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        if (!is_string($hash)) {
            throw new RuntimeException('The new password could not be secured.');
        }

        $newVersion = (new UserRepository())->updatePasswordAndInvalidateSessions($userId, $hash);
        (new AuthService())->refreshSessionVersion($userId, $newVersion);
        (new SecurityAuditService())->record('account.password_changed', $userId, $userId);
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

        // Per-account quota enforced in the database, not only the browser session.
        // Time windows use MySQL's own clock to avoid time zone inconsistencies.
        $pdo = Database::connection();
        $limits = $pdo->prepare(
            'SELECT
                SUM(created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 90 SECOND)) AS recent_requests,
                SUM(created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1 DAY)) AS daily_requests
             FROM email_change_tokens WHERE user_id = :user_id'
        );
        $limits->execute(['user_id' => $userId]);
        $usage = $limits->fetch(\PDO::FETCH_ASSOC) ?: [];
        if ((int) ($usage['recent_requests'] ?? 0) > 0) {
            throw new DomainException('Please wait 90 seconds before requesting another email change.');
        }
        if ((int) ($usage['daily_requests'] ?? 0) >= 6) {
            throw new DomainException('Email change request limit reached. Try again tomorrow.');
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
            'template_key' => 'account_email_change_confirmation',
            'user_id' => (int) ($user['id'] ?? 0), // BARAMA_EMAIL_CHANGE_LOCALE_V140
            'variables' => [
                'recipient_name' => (string) ($user['name'] ?? ''),
                'new_email' => $newEmail,
                'action_url' => $confirmationUrl,
                'expires_minutes' => '60',
            ],
            'subject' => 'Confirm your Barama email address',
            'html' => '<p><strong>Confirm your new email address</strong></p>'
                . '<p>Open the secure confirmation page while signed in to your Barama account.</p>'
                . '<p><a href="' . e($confirmationUrl) . '">Confirm email address</a></p>'
                . '<p>This link expires in 60 minutes.</p>',
            'text' => "Confirm your new Barama email address:\n\n"
                . $confirmationUrl . "\n\nThis link expires in 60 minutes.",
            'dedupe_key' => 'email-change:' . $userId . ':' . hash('sha256', $newEmail . $token),
        ]);

        (new SecurityAuditService())->record(
            'account.email_change_requested',
            $userId,
            $userId,
            ['new_email_hash' => hash('sha256', $newEmail)]
        );
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
            $newVersion = (new UserRepository())->incrementAuthVersion($userId);
            $pdo->commit();
            (new AuthService())->refreshSessionVersion($userId, $newVersion);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        (new SecurityAuditService())->record(
            'account.email_changed',
            $userId,
            $userId
        );
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

        (new UserRepository())->setStatusAndInvalidate($userId, 'deactivated');
        (new SecurityAuditService())->record(
            'account.deactivated',
            $userId,
            $userId
        );
        (new AuthService())->logout();
    }


}
