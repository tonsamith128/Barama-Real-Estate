<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\NotificationRepository;
use Barama\Repositories\RoleRepository;
use PDO;

/**
 * In-app company moderation alerts, bound to the committed workflow transition.
 * The caller invokes this inside the same database transaction as status history.
 * No email settings, role assignments, or approval state are changed here.
 */
final class CompanyWorkflowNotificationService
{
    /** @return array{category:string,type:string,title:string,message:string,action_url:string,entity_type:string,entity_id:int,dedupe_key:string}|null */
    public static function payload(
        int $companyId,
        string $companyName,
        string $status,
        int $transitionId
    ): ?array {
        if ($companyId < 1 || $transitionId < 1
            || !in_array($status, ['pending', 'approved', 'rejected'], true)) {
            return null;
        }

        $name = mb_substr(trim($companyName), 0, 120);
        if ($name === '') {
            $name = 'Company #' . $companyId;
        }

        $pending = $status === 'pending';
        return [
            'category' => $pending ? 'workflow_reviews' : 'workflow_updates',
            'type' => 'company_' . $status,
            'title' => match ($status) {
                'pending' => 'Company awaiting approval',
                'approved' => 'Your company is approved',
                'rejected' => 'Company changes requested',
            },
            'message' => match ($status) {
                'pending' => $name . ' has been submitted for review.',
                'approved' => $name . ' has been approved. You can manage your company workspace.',
                'rejected' => $name . ' needs changes. Open the company workspace to review its status.',
            },
            'action_url' => $pending
                ? '/dashboard/manage/company/' . $companyId
                : '/developer/company',
            'entity_type' => 'company',
            'entity_id' => $companyId,
            'dedupe_key' => 'company-workflow:' . $companyId . ':' . $transitionId,
        ];
    }

    public function onTransition(int $companyId, string $status, int $transitionId): void
    {
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)
            || $companyId < 1 || $transitionId < 1) {
            return;
        }

        $pdo = Database::connection();
        $query = $pdo->prepare('SELECT name FROM companies WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $query->execute(['id' => $companyId]);
        $name = $query->fetchColumn();
        if (!is_string($name)) {
            return;
        }

        $notification = self::payload($companyId, $name, $status, $transitionId);
        if ($notification === null) {
            return;
        }

        $repository = new NotificationRepository();
        if ($status === 'pending') {
            $recipients = $pdo->query(
                'SELECT DISTINCT u.id FROM users u
                 INNER JOIN user_roles ur ON ur.user_id = u.id
                 INNER JOIN roles r ON r.id = ur.role_id
                 WHERE r.slug = "administrator" AND u.status = "active"'
            )->fetchAll(PDO::FETCH_COLUMN);
            $roles = new RoleRepository();
            foreach ($recipients as $recipient) {
                $userId = (int) $recipient;
                if (!$roles->userCan($userId, 'dashboard.access')
                    || !$roles->userCan($userId, 'company.manage')) {
                    continue;
                }
                $this->addForUser($repository, $userId, $notification);
            }
            return;
        }

        $statement = $pdo->prepare(
            'SELECT DISTINCT cm.user_id FROM company_members cm
             INNER JOIN users u ON u.id = cm.user_id
             WHERE cm.company_id = :company_id
               AND cm.member_role = "owner"
               AND cm.status = "active"
               AND u.status = "active"'
        );
        $statement->execute(['company_id' => $companyId]);
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $recipient) {
            $this->addForUser($repository, (int) $recipient, $notification);
        }
    }

    /** @param array<string,mixed> $notification */
    private function addForUser(NotificationRepository $repository, int $userId, array $notification): void
    {
        if ($userId < 1 || !$repository->preferences($userId)['in_app_enabled']) {
            return;
        }

        $repository->createNotification(array_merge($notification, [
            'user_id' => $userId,
        ]));
    }
}
