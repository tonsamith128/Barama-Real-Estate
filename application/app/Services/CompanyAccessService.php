<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\CompanyMembershipRepository;
use DomainException;

final class CompanyAccessService
{
    public function __construct(
        private readonly ?AuthService $auth = null,
        private readonly ?CompanyMembershipRepository $memberships = null
    ) {
    }

    public function canManage(int $companyId): bool
    {
        $auth = $this->auth ?? new AuthService();
        $user = $auth->user();

        if ($user === null) {
            return false;
        }

        if ($auth->hasRole('administrator')) {
            return true;
        }

        if (!$auth->can('company.manage')) {
            return false;
        }

        $memberships = $this->memberships ?? new CompanyMembershipRepository();

        return $memberships->isActiveMember($companyId, (int) $user['id']);
    }

    public function assertCanManage(int $companyId): void
    {
        if (!$this->canManage($companyId)) {
            throw new DomainException(
                'The authenticated account cannot manage this company.'
            );
        }
    }
}
