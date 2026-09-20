<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Csrf;
use Barama\Core\Database;
use Barama\Core\Session;
use Barama\Repositories\RoleRepository;
use Barama\Repositories\UserRepository;
use Barama\Security\LoginThrottle;
use PDOException;
use RuntimeException;

final class AuthService
{
    private const SESSION_USER_ID = 'auth.user_id';

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

        $user = $this->users->findById((int) $userId);

        if ($user === null || (string) $user['status'] !== 'active') {
            Session::forget(self::SESSION_USER_ID);
            $this->resolvedUser = null;
            return null;
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

        return $userId;
    }

    public function attempt(string $email, string $password, string $ip): string
    {
        $email = strtolower(trim($email));

        if ($this->throttle->isLocked($email, $ip)) {
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

            return 'invalid';
        }

        $validPassword = password_verify($password, (string) $user['password_hash']);

        if (!$validPassword || (string) $user['status'] !== 'active') {
            $this->throttle->hit($email, $ip);
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

        return 'success';
    }

    public function logout(): void
    {
        Session::forget(self::SESSION_USER_ID);
        Session::forget('auth.intended');
        Session::regenerate();
        Csrf::regenerate();
        $this->resolvedUser = null;
    }

    public function can(string $permissionSlug): bool
    {
        $user = $this->user();

        return $user !== null
            && $this->roles->userCan((int) $user['id'], $permissionSlug);
    }

    public function hasRole(string $roleSlug): bool
    {
        $user = $this->user();

        return $user !== null
            && $this->roles->userHasRole((int) $user['id'], $roleSlug);
    }

    public function homePath(): string
    {
        if ($this->can('dashboard.access')) {
            return '/dashboard';
        }

        if ($this->can('developer.access')) {
            return '/developer';
        }

        return '/account';
    }

    private function loginUserId(int $userId): void
    {
        Session::regenerate();
        Csrf::regenerate();
        Session::put(self::SESSION_USER_ID, $userId);
        $this->resolvedUser = false;
    }
}
