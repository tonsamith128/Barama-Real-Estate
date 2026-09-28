<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\NotificationRepository;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class CommunicationsManagementService
{
    public function requeueEmail(int $id): void
    {
        $actor = $this->administrator();
        $repository = new NotificationRepository();
        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $message = $repository->outboxMessage($id, true);

            if ($message === null) {
                throw new InvalidArgumentException('Mail queue item was not found.');
            }

            $status = (string) $message['status'];

            if (!in_array($status, ['failed', 'cancelled'], true)) {
                throw new DomainException(
                    'Only failed or cancelled mail can be requeued manually.'
                );
            }

            $repository->requeueEmail($id);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        (new SecurityAuditService())->record(
            'admin.communication_mail_requeued',
            (int) $actor['id'],
            null,
            [
                'email_outbox_id' => $id,
                'to_address' => (string) $message['to_address'],
                'previous_status' => (string) $message['status'],
            ]
        );
    }

    public function cancelEmail(int $id): void
    {
        $actor = $this->administrator();
        $repository = new NotificationRepository();
        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $message = $repository->outboxMessage($id, true);

            if ($message === null) {
                throw new InvalidArgumentException('Mail queue item was not found.');
            }

            $status = (string) $message['status'];

            if (!in_array($status, ['pending', 'failed'], true)) {
                throw new DomainException(
                    'Only pending or failed mail can be cancelled.'
                );
            }

            $repository->cancelEmail($id);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }

        (new SecurityAuditService())->record(
            'admin.communication_mail_cancelled',
            (int) $actor['id'],
            null,
            [
                'email_outbox_id' => $id,
                'to_address' => (string) $message['to_address'],
                'previous_status' => (string) $message['status'],
            ]
        );
    }

    /** @return array<string,mixed> */
    private function administrator(): array
    {
        $auth = new AuthService();
        $user = $auth->user();

        if (
            $user === null
            || !$auth->hasRole('administrator')
            || !$auth->can('communication.manage')
        ) {
            throw new DomainException(
                'Administrator communications permission is required.'
            );
        }

        return $user;
    }
}
