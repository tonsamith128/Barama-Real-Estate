<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;
use PDO;

final class CompanyMembershipRepository
{
    /** @return array<string, mixed>|null */
    public function findActiveMembership(int $companyId, int $userId): ?array
    {
        $statement = Database::connection()->prepare(
            'SELECT company_id, user_id, member_role, status, joined_at,
                    created_at, updated_at
             FROM company_members
             WHERE company_id = :company_id
               AND user_id = :user_id
               AND status = :status
             LIMIT 1'
        );
        $statement->execute([
            'company_id' => $companyId,
            'user_id' => $userId,
            'status' => 'active',
        ]);

        $membership = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($membership) ? $membership : null;
    }

    public function isActiveMember(int $companyId, int $userId): bool
    {
        return $this->findActiveMembership($companyId, $userId) !== null;
    }
}
