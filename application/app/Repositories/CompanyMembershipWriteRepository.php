<?php

declare(strict_types=1);

namespace Barama\Repositories;

use Barama\Core\Database;

final class CompanyMembershipWriteRepository
{
    public function upsert(
        int $companyId,
        int $userId,
        string $memberRole,
        string $status = 'active'
    ): void {
        $statement = Database::connection()->prepare(
            'INSERT INTO company_members
                (company_id, user_id, member_role, status, joined_at)
             VALUES
                (:company_id, :user_id, :member_role, :status, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE
                member_role = VALUES(member_role),
                status = VALUES(status),
                joined_at = COALESCE(joined_at, CURRENT_TIMESTAMP),
                updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'company_id' => $companyId,
            'user_id' => $userId,
            'member_role' => $memberRole,
            'status' => $status,
        ]);
    }

    public function updateRole(int $companyId, int $userId, string $memberRole): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE company_members
             SET member_role = :member_role, updated_at = CURRENT_TIMESTAMP
             WHERE company_id = :company_id
               AND user_id = :user_id
               AND status = "active"'
        );
        $statement->execute([
            'member_role' => $memberRole,
            'company_id' => $companyId,
            'user_id' => $userId,
        ]);
    }

    public function deactivate(int $companyId, int $userId): void
    {
        $statement = Database::connection()->prepare(
            'UPDATE company_members
             SET status = :status, updated_at = CURRENT_TIMESTAMP
             WHERE company_id = :company_id
               AND user_id = :user_id'
        );
        $statement->execute([
            'status' => 'inactive',
            'company_id' => $companyId,
            'user_id' => $userId,
        ]);
    }
}
