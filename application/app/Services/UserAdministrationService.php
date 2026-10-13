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

    /** Create an actual customer, without authenticating the administrator as the new user. */
    public function createCustomer(string $name, string $email, string $password, string $confirmation): int
    {
        $actor = $this->administrator();
        $name = trim($name);
        $email = strtolower(trim($email));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            throw new InvalidArgumentException('Name must contain 2–120 characters.');
        }
        if (strlen($email) > 191 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }
        $passwordErrors = \Barama\Security\PasswordPolicy::errors($password, $confirmation);
        if ($passwordErrors !== []) {
            throw new InvalidArgumentException(implode(' ', array_slice($passwordErrors, 0, 3)));
        }
        $users = new UserRepository();
        if ($users->findByEmail($email) !== null) {
            throw new InvalidArgumentException('An account with this email address already exists.');
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash)) {
            throw new DomainException('Could not securely prepare the account password.');
        }
        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            $id = $users->create($name, $email, $hash);
            (new RoleRepository())->assignRole($id, 'user');
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $error;
        }
        (new SecurityAuditService())->record('admin.user_created', (int) $actor['id'], $id, ['role' => 'user']);
        return $id;
    }

    /** Manual verification is explicit and recorded; it is not an automatic role grant. */
    public function verifyEmailManually(int $targetUserId): void
    {
        $actor = $this->administrator();
        $user = (new UserRepository())->findById($targetUserId);
        if ($user === null) { throw new InvalidArgumentException('User not found.'); }
        if ((string) $user['status'] !== 'active') { throw new DomainException('Restore the account before verifying email.'); }
        if (!empty($user['email_verified_at'])) { return; }
        // Keep verification, session invalidation and its audit entry atomic.
        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            (new UserRepository())->markEmailVerified($targetUserId);
            (new UserRepository())->incrementAuthVersion($targetUserId);
            (new SecurityAuditService())->record('admin.email_manually_verified', (int) $actor['id'], $targetUserId);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

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
        // BARAMA_A45_ROLE_MANAGEMENT_PERMISSION
        if (!(new AuthService())->can('roles.manage')) {
            throw new DomainException('Role management permission is required.');
        }
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
        $this->administrator();
        $target = (new UserRepository())->findById($targetUserId);
        if ($target === null) { throw new InvalidArgumentException('User not found.'); }
        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            $changed = (new CompanyWorkspaceAccessService())->reconcile($targetUserId);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $error;
        }
        $eligible = (new CompanyWorkspaceAccessService())->eligibleRoles($targetUserId);
        (new SecurityAuditService())->record('admin.company_workspace_access_reconciled', (int) auth_user()['id'], $targetUserId, [
            'roles' => $eligible, 'changed' => $changed,
        ]);
        return [
            'developer' => $eligible !== [],
            'company_memberships' => (new UserRepository())->activeCompanyMembershipCount($targetUserId),
            'changed' => $changed,
        ];
    }

    /** Explicit administrator downgrade; never implicitly transfers sole company ownership. */
    public function downgradeToCustomer(int $targetUserId): void
    {
        $actor = $this->administrator();
        if ((int) $actor['id'] === $targetUserId) { throw new DomainException('You cannot downgrade your own administrator account.'); }
        $users = new UserRepository();
        $target = $users->findById($targetUserId);
        if ($target === null) { throw new InvalidArgumentException('User not found.'); }
        $roles = new RoleRepository();
        if ($roles->userHasRole($targetUserId, 'administrator') || $roles->userHasRole($targetUserId, 'super-administrator')) {
            throw new DomainException('Administrator roles cannot be downgraded through this action.');
        }
        if ($users->soleOwnedCompanies($targetUserId) !== []) {
            throw new DomainException('Transfer sole company ownership before downgrading this account.');
        }
        $pdo = Database::connection();
        try {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE company_members SET status = "inactive", updated_at = CURRENT_TIMESTAMP WHERE user_id = :id AND status = "active"')
                ->execute(['id' => $targetUserId]);
            (new CompanyWorkspaceAccessService())->reconcile($targetUserId);
            $users->incrementAuthVersion($targetUserId);
            $pdo->commit();
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $error;
        }
        (new SecurityAuditService())->record('admin.company_access_downgraded', (int) $actor['id'], $targetUserId);
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

        if ($user === null || (!$auth->hasRole('administrator') && !$auth->hasRole('super-administrator'))) { // BARAMA_A45_ADMIN_SCOPE
            throw new DomainException('Administrator access is required.');
        }

        return $user;
    }
}
