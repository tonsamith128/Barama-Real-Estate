<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\CompanyMembershipRepository;
use Barama\Repositories\CompanyMembershipWriteRepository;
use Barama\Repositories\RoleRepository;
use Barama\Repositories\UserRepository;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class MembershipService
{
    private const MANAGER_ROLES = ['owner', 'manager'];
    private const MEMBER_ROLES = ['owner', 'manager', 'agent', 'member'];

    public function addByEmail(int $companyId, string $email, string $memberRole): void
    {
        $this->assertCanManageMembers($companyId);

        if (!in_array($memberRole, self::MEMBER_ROLES, true) || $memberRole === 'owner') {
            throw new InvalidArgumentException('Invalid assignable company member role.');
        }

        $users = new UserRepository();
        $user = $users->findByEmail($email);

        if ($user === null || (string) $user['status'] !== 'active') {
            throw new InvalidArgumentException('No active user account matches that email.');
        }

        $roles = new RoleRepository();
        $memberships = new CompanyMembershipWriteRepository();
        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            if (!$roles->userHasRole((int) $user['id'], 'developer')) {
                $roles->assignRole((int) $user['id'], 'developer');
            }

            $memberships->upsert($companyId, (int) $user['id'], $memberRole);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function deactivate(int $companyId, int $userId): void
    {
        $this->assertCanManageMembers($companyId);

        $membership = (new CompanyMembershipRepository())
            ->findActiveMembership($companyId, $userId);

        if ($membership === null) {
            throw new InvalidArgumentException('Company member was not found.');
        }

        if ((string) $membership['member_role'] === 'owner') {
            throw new DomainException('The company owner cannot be removed through member management.');
        }

        (new CompanyMembershipWriteRepository())->deactivate($companyId, $userId);
    }

    private function assertCanManageMembers(int $companyId): void
    {
        $auth = new AuthService();
        $user = $auth->user();

        if ($user === null) {
            throw new DomainException('Authentication is required.');
        }

        if ($auth->hasRole('administrator')) {
            return;
        }

        $membership = (new CompanyMembershipRepository())
            ->findActiveMembership($companyId, (int) $user['id']);

        if (
            $membership === null
            || !in_array((string) $membership['member_role'], self::MANAGER_ROLES, true)
        ) {
            throw new DomainException('Company owner or manager access is required.');
        }
    }
}
