<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\RoleRepository;
use Barama\Repositories\UserRepository;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class UserAdministrationService
{
    private const STATUSES = ['active', 'suspended', 'deactivated'];

    public function setStatus(int $targetUserId, string $status): void
    {
        $actor = $this->administrator();
        $status = strtolower(trim($status));

        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Unsupported account status.');
        }

        $users = new UserRepository();
        $target = $users->findById($targetUserId);

        if ($target === null) {
            throw new InvalidArgumentException('User account was not found.');
        }

        if ((int) $actor['id'] === $targetUserId && $status !== 'active') {
            throw new DomainException('You cannot suspend or deactivate your own administrator account.');
        }

        $roles = new RoleRepository();

        if (
            $status !== 'active'
            && $roles->userHasRole($targetUserId, 'administrator')
            && $roles->activeRoleUserCount('administrator') <= 1
        ) {
            throw new DomainException('The final active administrator account cannot be disabled.');
        }

        $users->setStatusAndInvalidate($targetUserId, $status);

        (new SecurityAuditService())->record(
            'admin.user_status_changed',
            (int) $actor['id'],
            $targetUserId,
            ['status' => $status]
        );
    }

    public function setAdministrator(int $targetUserId, bool $enabled): void
    {
        $actor = $this->administrator();
        $users = new UserRepository();
        $target = $users->findById($targetUserId);

        if ($target === null) {
            throw new InvalidArgumentException('User account was not found.');
        }

        $roles = new RoleRepository();
        $currentlyAdmin = $roles->userHasRole($targetUserId, 'administrator');

        if ($enabled && empty($target['email_verified_at'])) {
            throw new DomainException('Verify the user email before granting administrator access.');
        }

        if ($enabled === $currentlyAdmin) {
            return;
        }

        if (!$enabled) {
            if ((int) $actor['id'] === $targetUserId) {
                throw new DomainException('You cannot remove your own administrator role.');
            }

            if (
                (string) $target['status'] === 'active'
                && $roles->activeRoleUserCount('administrator') <= 1
            ) {
                throw new DomainException('The final active administrator role cannot be removed.');
            }
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            if ($enabled) {
                $roles->assignRole($targetUserId, 'administrator');
            } else {
                $roles->removeRole($targetUserId, 'administrator');
            }

            $users->incrementAuthVersion($targetUserId);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        (new SecurityAuditService())->record(
            $enabled ? 'admin.role_granted' : 'admin.role_revoked',
            (int) $actor['id'],
            $targetUserId,
            ['role' => 'administrator']
        );
    }

    /** @return array<string,mixed> */
    private function administrator(): array
    {
        $auth = new AuthService();
        $user = $auth->user();

        if ($user === null || !$auth->hasRole('administrator')) {
            throw new DomainException('Administrator access is required.');
        }

        return $user;
    }
}
