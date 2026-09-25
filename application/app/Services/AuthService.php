<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Csrf;
use Barama\Core\Database;
use Barama\Core\Session;
use Barama\Repositories\AccountSettingsRepository;
use Barama\Repositories\RoleRepository;
use Barama\Repositories\UserRepository;
use Barama\Security\LoginThrottle;
use Barama\Support\Locale;
use PDOException;
use RuntimeException;

final class AuthService
{
    private const SESSION_USER_ID = 'auth.user_id';
    private const SESSION_VERSION = 'auth.version';
    private const SESSION_LOGIN_AT = 'auth.login_at';
    private const SESSION_LAST_ACTIVITY = 'auth.last_activity';
    private const SESSION_CLI_USER_ID = 'auth.cli_user_id';

    private UserRepository $users;
    private RoleRepository $roles;
    private LoginThrottle $throttle;

    /** @var array<string, mixed>|null|false */
    private array|null|false $resolvedUser = false;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->roles = new RoleRepository();
        $this->throttle = new LoginThrottle();
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        if ($this->resolvedUser !== false) {
            return $this->resolvedUser;
        }

        $userId = Session::get(self::SESSION_USER_ID);

        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            $this->resolvedUser = null;
            return null;
        }

        $cli = defined('BARAMA_CLI') && BARAMA_CLI === true;

        if (!$cli && $this->sessionExpired()) {
            $this->clearAuthSession();
            $this->resolvedUser = null;
            return null;
        }

        $user = $this->users->findById((int) $userId);
        $sessionVersion = (int) Session::get(self::SESSION_VERSION, 0);

        if (
            $cli
            && $user !== null
            && (
                $sessionVersion < 1
                || (int) Session::get(self::SESSION_CLI_USER_ID, 0) !== (int) $userId
            )
        ) {
            $sessionVersion = (int) $user['auth_version'];
            Session::put(self::SESSION_VERSION, $sessionVersion);
            Session::put(self::SESSION_CLI_USER_ID, (int) $userId);
        }

        if (
            $user === null
            || (string) $user['status'] !== 'active'
            || $sessionVersion < 1
            || $sessionVersion !== (int) $user['auth_version']
        ) {
            $this->clearAuthSession();
            $this->resolvedUser = null;
            return null;
        }

        if (!$cli && !Locale::hasSession()) {
            $preferred = Locale::normalize((string) (
                (new AccountSettingsRepository())->preferredLanguage((int) $user['id']) ?? ''
            ));

            if ($preferred !== null) {
                Locale::set($preferred);
            }
        }

        if (!$cli) {
            Session::put(self::SESSION_LAST_ACTIVITY, time());
        }
        $this->resolvedUser = $user;

        return $user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function register(string $name, string $email, string $password): int
    {
        if ($this->users->findByEmail($email) !== null) {
            throw new RuntimeException('An account with this email address already exists.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        if (!is_string($passwordHash)) {
            throw new RuntimeException('The password could not be secured.');
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();
            $userId = $this->users->create($name, $email, $passwordHash);
            $this->roles->assignRole($userId, 'user');
            $pdo->commit();
        } catch (PDOException|RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        $this->loginUserId($userId);

        (new SecurityAuditService())->record(
            'auth.registered',
            $userId,
            $userId
        );

        return $userId;
    }

    public function attempt(string $email, string $password, string $ip): string
    {
        $email = strtolower(trim($email));

        if ($this->throttle->isLocked($email, $ip)) {
            (new SecurityAuditService())->record(
                'auth.login_locked',
                null,
                null,
                ['email_hash' => hash('sha256', $email)],
                $ip
            );
            return 'locked';
        }

        $user = $this->users->findByEmail($email);

        if ($user === null) {
            static $dummyHash = null;

            if (!is_string($dummyHash)) {
                $dummyHash = password_hash('barama-invalid-user', PASSWORD_DEFAULT);
            }

            if (is_string($dummyHash)) {
                password_verify($password, $dummyHash);
            }

            $this->throttle->hit($email, $ip);
            (new SecurityAuditService())->record(
                'auth.login_failed',
                null,
                null,
                ['email_hash' => hash('sha256', $email)],
                $ip
            );
            return 'invalid';
        }

        $validPassword = password_verify($password, (string) $user['password_hash']);

        if (!$validPassword || (string) $user['status'] !== 'active') {
            $this->throttle->hit($email, $ip);
            (new SecurityAuditService())->record(
                'auth.login_failed',
                null,
                (int) $user['id'],
                ['reason' => $validPassword ? 'status' : 'credentials'],
                $ip
            );
            return 'invalid';
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);

            if (is_string($newHash)) {
                $this->users->updatePassword((int) $user['id'], $newHash);
            }
        }

        $this->throttle->clear($email, $ip);
        $this->users->updateLastLogin((int) $user['id']);
        $this->loginUserId((int) $user['id']);

        (new SecurityAuditService())->record(
            'auth.login_success',
            (int) $user['id'],
            (int) $user['id'],
            [],
            $ip
        );

        return 'success';
    }

    public function logout(): void
    {
        $userId = Session::get(self::SESSION_USER_ID);
        $this->clearAuthSession();
        Session::regenerate();
        Csrf::regenerate();
        $this->resolvedUser = null;

        if (is_int($userId) || ctype_digit((string) $userId)) {
            (new SecurityAuditService())->record(
                'auth.logout',
                (int) $userId,
                (int) $userId
            );
        }
    }

    public function can(string $permissionSlug): bool
    {
        $user = $this->user();

        return $user !== null
            && $this->roles->userCan((int) $user['id'], $permissionSlug);
    }

    /* BARAMA_WORKSPACE_BOUNDARY_V100 */
    public function canAccessWorkspace(string $workspace): bool
    {
        return match ($workspace) {
            'account' => $this->can('account.access')
                && (!$this->hasRole('developer') || $this->hasRole('administrator')),
            'developer' => $this->can('developer.access')
                && ($this->hasRole('developer') || $this->hasRole('administrator')),
            'dashboard' => $this->can('dashboard.access')
                && $this->hasRole('administrator'),
            default => false,
        };
    }

    public function hasRole(string $roleSlug): bool
    {
        $user = $this->user();

        return $user !== null
            && $this->roles->userHasRole((int) $user['id'], $roleSlug);
    }

    public function requiresEmailVerification(): bool
    {
        $user = $this->user();

        return $user !== null && empty($user['email_verified_at']);
    }

    public function homePath(): string
    {
        if ($this->requiresEmailVerification()) {
            return '/verify-email';
        }

        if ($this->can('dashboard.access')) {
            return '/dashboard';
        }

        if ($this->can('developer.access')) {
            return '/developer';
        }

        return '/account';
    }

    public function refreshSessionVersion(int $userId, int $authVersion): void
    {
        $sessionUserId = Session::get(self::SESSION_USER_ID);

        if ((int) $sessionUserId === $userId) {
            Session::put(self::SESSION_VERSION, max(1, $authVersion));
            Session::put(self::SESSION_LAST_ACTIVITY, time());
            $this->resolvedUser = false;
        }
    }

    private function loginUserId(int $userId): void
    {
        $user = $this->users->findById($userId);

        if ($user === null) {
            throw new RuntimeException('The account could not be loaded.');
        }

        Session::regenerate();
        Csrf::regenerate();
        Session::put(self::SESSION_USER_ID, $userId);
        Session::put(self::SESSION_VERSION, (int) $user['auth_version']);
        Session::put(self::SESSION_LOGIN_AT, time());
        Session::put(self::SESSION_LAST_ACTIVITY, time());

        $preferred = Locale::normalize((string) (
            (new AccountSettingsRepository())->preferredLanguage($userId) ?? ''
        ));

        if ($preferred !== null) {
            Locale::set($preferred);
        } elseif (!Locale::hasSession()) {
            Locale::set(Locale::current());
        }

        $this->resolvedUser = false;
    }

    private function sessionExpired(): bool
    {
        $now = time();
        $loginAt = (int) Session::get(self::SESSION_LOGIN_AT, 0);
        $lastActivity = (int) Session::get(self::SESSION_LAST_ACTIVITY, 0);
        $idle = max(300, (int) config('auth.session_idle_seconds', 7200));
        $absolute = max($idle, (int) config('auth.session_absolute_seconds', 43200));

        return $loginAt < 1
            || $lastActivity < 1
            || ($now - $lastActivity) > $idle
            || ($now - $loginAt) > $absolute;
    }

    private function clearAuthSession(): void
    {
        foreach ([
            self::SESSION_USER_ID,
            self::SESSION_VERSION,
            self::SESSION_LOGIN_AT,
            self::SESSION_LAST_ACTIVITY,
            self::SESSION_CLI_USER_ID,
            'auth.intended',
        ] as $key) {
            Session::forget($key);
        }
    }
}
