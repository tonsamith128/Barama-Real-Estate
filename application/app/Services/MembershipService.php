<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\AccountSettingsRepository;
use Barama\Repositories\CompanyMembershipRepository;
use Barama\Repositories\CompanyMembershipWriteRepository;
use Barama\Repositories\CompanyRepository;
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
        $this->inviteByEmail($companyId, $email, $memberRole);
    }

    public function inviteByEmail(int $companyId, string $email, string $memberRole): int
    {
        $actor = $this->assertCanManageMembers($companyId);

        if (!in_array($memberRole, self::MEMBER_ROLES, true) || $memberRole === 'owner') {
            throw new InvalidArgumentException('Invalid assignable company member role.');
        }

        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid team member email address.');
        }

        $company = (new CompanyRepository())->findById($companyId);

        if ($company === null || (string) $company['status'] !== 'approved') {
            throw new DomainException('Team invitations are available after company approval.');
        }

        $users = new UserRepository();
        $target = $users->findByEmail($email);
        $memberships = new CompanyMembershipRepository();

        if (
            $target !== null
            && $memberships->isActiveMember($companyId, (int) $target['id'])
        ) {
            throw new InvalidArgumentException('This user is already an active company member.');
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            (new SubscriptionEntitlementService())->assertCanConsume(
                $companyId,
                'team_seat'
            );

            if (
                $target !== null
                && $memberships->isActiveMember($companyId, (int) $target['id'])
            ) {
                throw new InvalidArgumentException(
                    'This user is already an active company member.'
                );
            }

            $invitationId = (new AccountSettingsRepository())->createInvitation(
                $companyId,
                (int) $actor['id'],
                $email,
                $memberRole,
                date('Y-m-d H:i:s', time() + 7 * 86400)
            );

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        (new NotificationService())->notifyAddress($email, [
            'subject' => 'Company invitation · Barama Real Estate',
            'html' => '<p><strong>You have been invited to ' . e((string) $company['name']) . '</strong></p>'
                . '<p>Sign in or create an account using this email address, then open Company Access in your account to accept the invitation.</p>'
                . '<p><a href="' . e(url('/account/company')) . '">Review company invitation</a></p>'
                . '<p>The invitation expires in 7 days.</p>',
            'text' => "You have been invited to " . (string) $company['name']
                . ".\n\nReview the invitation: " . url('/account/company')
                . "\n\nThe invitation expires in 7 days.",
            'dedupe_key' => 'company-invitation:' . $invitationId,
        ]);

        return $invitationId;
    }

    public function acceptInvitation(int $invitationId): int
    {
        $auth = new AuthService();
        $user = $auth->user();

        if ($user === null) {
            throw new DomainException('Authentication is required.');
        }

        if (empty($user['email_verified_at'])) {
            throw new DomainException('Verify your email address before accepting a company invitation.');
        }

        $repository = new AccountSettingsRepository();
        $invitation = $repository->pendingInvitation(
            $invitationId,
            (string) $user['email']
        );

        if ($invitation === null) {
            throw new InvalidArgumentException('This company invitation is unavailable or expired.');
        }

        if ((string) $invitation['company_status'] !== 'approved') {
            throw new DomainException('This company is not currently eligible for team access.');
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $roles = new RoleRepository();

            if (!$roles->userHasRole((int) $user['id'], 'developer')) {
                $roles->assignRole((int) $user['id'], 'developer');
            }

            (new CompanyMembershipWriteRepository())->upsert(
                (int) $invitation['company_id'],
                (int) $user['id'],
                (string) $invitation['member_role']
            );

            $repository->acceptInvitation($invitationId, (int) $user['id']);
            $pdo->commit();

            return (int) $invitation['company_id'];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function revokeInvitation(int $companyId, int $invitationId): void
    {
        $this->assertCanManageMembers($companyId);
        (new AccountSettingsRepository())->revokeInvitation($invitationId, $companyId);
    }

    public function updateRole(int $companyId, int $userId, string $memberRole): void
    {
        if (!in_array($memberRole, ['manager', 'agent', 'member'], true)) {
            throw new InvalidArgumentException('Invalid assignable company member role.');
        }

        $actor = $this->assertCanManageMembers($companyId);
        $target = (new CompanyMembershipRepository())
            ->findActiveMembership($companyId, $userId);

        if ($target === null) {
            throw new InvalidArgumentException('Company member was not found.');
        }

        if ((string) $target['member_role'] === 'owner') {
            throw new DomainException('The company owner role cannot be changed here.');
        }

        $auth = new AuthService();

        if (!$auth->hasRole('administrator')) {
            $actorMembership = (new CompanyMembershipRepository())
                ->findActiveMembership($companyId, (int) $actor['id']);

            if (
                $actorMembership === null
                || !in_array((string) $actorMembership['member_role'], self::MANAGER_ROLES, true)
            ) {
                throw new DomainException('Company owner or manager access is required.');
            }

            if (
                (string) $actorMembership['member_role'] !== 'owner'
                && (
                    (string) $target['member_role'] === 'manager'
                    || $memberRole === 'manager'
                )
            ) {
                throw new DomainException(
                    'Only the company owner can assign or change the manager role.'
                );
            }
        }

        (new CompanyMembershipWriteRepository())->updateRole(
            $companyId,
            $userId,
            $memberRole
        );
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

    /** @return array<string,mixed> */
    private function assertCanManageMembers(int $companyId): array
    {
        $auth = new AuthService();
        $user = $auth->user();

        if ($user === null) {
            throw new DomainException('Authentication is required.');
        }

        if ($auth->hasRole('administrator')) {
            return $user;
        }

        $membership = (new CompanyMembershipRepository())
            ->findActiveMembership($companyId, (int) $user['id']);

        if (
            $membership === null
            || !in_array((string) $membership['member_role'], self::MANAGER_ROLES, true)
        ) {
            throw new DomainException('Company owner or manager access is required.');
        }

        return $user;
    }
}
