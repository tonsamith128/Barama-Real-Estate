<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\RoleRepository;
use Barama\Repositories\UserRepository;
use PDO;

/** Company membership is authoritative; a role by itself never opens a workspace. */
final class CompanyWorkspaceAccessService
{
    /** @return array<int,string> */
    public function eligibleRoles(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DISTINCT c.company_type
             FROM company_members cm
             INNER JOIN companies c ON c.id = cm.company_id
             INNER JOIN users u ON u.id = cm.user_id
             WHERE cm.user_id = :user_id
               AND cm.status = "active"
               AND c.status = "approved"
               AND c.deleted_at IS NULL
               AND u.status = "active"
               AND u.email_verified_at IS NOT NULL'
        );
        $stmt->execute(['user_id' => $userId]);
        $types = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $roles = [];
        foreach ($types as $type) {
            $roles[$type === 'developer' ? 'developer' : 'company'] = true;
        }
        return array_keys($roles);
    }

    /** Syncs the user and workspace roles inside the caller's database transaction. */
    public function reconcile(int $userId): bool
    {
        $repository = new RoleRepository();
        if ($repository->userHasRole($userId, 'administrator')
            || $repository->userHasRole($userId, 'super-administrator')) {
            return false;
        }
        $eligible = $this->eligibleRoles($userId);
        $changed = false;
        foreach (['developer', 'company'] as $role) {
            $shouldHave = in_array($role, $eligible, true);
            $has = $repository->userHasRole($userId, $role);
            if ($shouldHave && !$has) { $repository->assignRole($userId, $role); $changed = true; }
            if (!$shouldHave && $has) { $repository->removeRole($userId, $role); $changed = true; }
        }
        $hasCustomer = $repository->userHasRole($userId, 'user');
        if ($eligible !== [] && $hasCustomer) {
            $repository->removeRole($userId, 'user');
            $changed = true;
        } elseif ($eligible === [] && !$hasCustomer) {
            $repository->assignRole($userId, 'user');
            $changed = true;
        }
        if ($changed) { (new UserRepository())->incrementAuthVersion($userId); }
        return $changed;
    }
}
