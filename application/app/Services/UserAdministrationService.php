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

        $previousStatus = (string) $target['status'];

        if ($status === $previousStatus) {
            return;
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

        if ($status !== 'active') {
            $soleOwnedCompanies = $users->soleOwnedCompanies($targetUserId);

            if ($soleOwnedCompanies !== []) {
                $names = array_map(
                    static fn (array $company): string => (string) $company['name'],
                    array_slice($soleOwnedCompanies, 0, 3)
                );
                $suffix = count($soleOwnedCompanies) > 3 ? ' and other companies' : '';

                throw new DomainException(
                    'Transfer company ownership before disabling this account. Sole ownership is still assigned to: '
                    . implode(', ', $names)
                    . $suffix
                    . '.'
                );
            }
        }

        $users->setStatusAndInvalidate($targetUserId, $status);

        (new SecurityAuditService())->record(
            'admin.user_status_changed',
            (int) $actor['id'],
            $targetUserId,
            [
                'previous_status' => $previousStatus,
                'status' => $status,
            ]
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

        if ($enabled && (string) $target['status'] !== 'active') {
            throw new DomainException('Restore the user account before granting administrator access.');
        }

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

    /** @return array{developer:bool,company_memberships:int,changed:bool} */
    public function reconcileDeveloperAccess(int $targetUserId): array
    {
        $actor = $this->administrator();
        $users = new UserRepository();
        $target = $users->findById($targetUserId);

        if ($target === null) {
            throw new InvalidArgumentException('User account was not found.');
        }

        $roles = new RoleRepository();
        $memberships = $users->activeCompanyMembershipCount($targetUserId);
        $hasDeveloper = $roles->userHasRole($targetUserId, 'developer');
        $shouldDeveloper = $memberships > 0;

        if ($shouldDeveloper && (string) $target['status'] !== 'active') {
            throw new DomainException(
                'Restore the user account before reconciling developer workspace access.'
            );
        }

        if ($shouldDeveloper && empty($target['email_verified_at'])) {
            throw new DomainException(
                'The user must verify their email before developer workspace access can be restored.'
            );
        }

        if ($hasDeveloper === $shouldDeveloper) {
            return [
                'developer' => $hasDeveloper,
                'company_memberships' => $memberships,
                'changed' => false,
            ];
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            if ($shouldDeveloper) {
                $roles->assignRole($targetUserId, 'developer');
            } else {
                $roles->removeRole($targetUserId, 'developer');
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
            'admin.developer_access_reconciled',
            (int) $actor['id'],
            $targetUserId,
            [
                'developer' => $shouldDeveloper,
                'company_memberships' => $memberships,
            ]
        );

        return [
            'developer' => $shouldDeveloper,
            'company_memberships' => $memberships,
            'changed' => true,
        ];
    }

    public function revokeSessions(int $targetUserId): void
    {
        $actor = $this->administrator();
        $users = new UserRepository();
        $target = $users->findById($targetUserId);

        if ($target === null) {
            throw new InvalidArgumentException('User account was not found.');
        }

        $users->incrementAuthVersion($targetUserId);

        (new SecurityAuditService())->record(
            'admin.user_sessions_revoked',
            (int) $actor['id'],
            $targetUserId,
            ['target_status' => (string) $target['status']]
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
