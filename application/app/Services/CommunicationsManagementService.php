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
            $repository->recordOutboxEvent(
                $id,
                'manual_requeue',
                null,
                (int) $actor['id'],
                $status,
                'pending',
                null,
                ['scope' => 'single']
            );
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
            $repository->recordOutboxEvent(
                $id,
                'manual_cancel',
                null,
                (int) $actor['id'],
                $status,
                'cancelled',
                null,
                ['scope' => 'single']
            );
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

    /* BARAMA_MAIL_QUEUE_OPERATIONS_V120 */
    /** @return array<string,mixed> */
    public function mailDetail(int $id): array
    {
        $this->administrator();
        $repository = new NotificationRepository();
        $message = $repository->adminOutboxDetail($id);
        if ($message === null) {
            throw new InvalidArgumentException('Mail queue item was not found.');
        }

        /* BARAMA_MAIL_QUEUE_AUDIT_HISTORY_V130 */
        $timeline = [];
        $labels = [
            'history_tracking_started' => 'Immutable history started',
            'queued' => 'Queued',
            'attempt_started' => 'Delivery attempt started',
            'failed' => 'Delivery attempt failed',
            'sent' => 'Delivery sent',
            'manual_requeue' => 'Manually requeued',
            'manual_cancel' => 'Manually cancelled',
            'bulk_requeue' => 'Bulk requeued',
            'bulk_cancel' => 'Bulk cancelled',
        ];
        foreach ($repository->outboxEvents($id) as $event) {
            $type = (string) ($event['event_type'] ?? 'event');
            $attempt = (int) ($event['attempt_number'] ?? 0);
            $from = trim((string) ($event['status_from'] ?? ''));
            $to = trim((string) ($event['status_to'] ?? ''));
            $actor = trim((string) ($event['actor_name'] ?? ''));
            $error = trim((string) ($event['error_text'] ?? ''));
            $detail = [];

            if ($type === 'history_tracking_started') {
                $detail[] = 'Forward-only immutable history begins here; earlier attempt-level events were not reconstructed.';
            } elseif ($type === 'queued') {
                $detail[] = 'Message entered the durable outbox.';
            } elseif ($type === 'attempt_started') {
                $detail[] = 'The canonical outbox worker claimed this message for delivery.';
            }
            if ($attempt > 0 && in_array($type, ['attempt_started', 'failed', 'sent'], true)) {
                $detail[] = 'Attempt #' . $attempt . '.';
            }
            if ($from !== '' || $to !== '') {
                $detail[] = ($from !== '' ? $from : '—') . ' → ' . ($to !== '' ? $to : '—') . '.';
            }
            if ($actor !== '') {
                $detail[] = 'Administrator: ' . $actor . '.';
            }
            if ($error !== '') {
                $detail[] = 'Error: ' . mb_substr($error, 0, 260);
            }

            $timeline[] = [
                'label' => $labels[$type] ?? ucwords(str_replace('_', ' ', $type)),
                'time' => (string) ($event['created_at'] ?? ''),
                'detail' => implode(' ', $detail),
            ];
        }

        if ($timeline === []) {
            $timeline[] = [
                'label' => 'Audit history unavailable',
                'time' => (string) ($message['updated_at'] ?? $message['created_at'] ?? ''),
                'detail' => 'No immutable event rows are available for this message yet.',
            ];
        }

        $recipientName = trim((string) ($message['user_name'] ?? ''));
        $address = trim((string) ($message['to_address'] ?? ''));
        return [
            'id' => (int) $message['id'],
            'status' => (string) $message['status'],
            'recipient_label' => $recipientName !== '' ? $recipientName . ' · ' . $address : $address,
            'to_address' => $address,
            'subject' => (string) $message['subject'],
            'attempts' => (int) $message['attempts'],
            'created_at' => (string) $message['created_at'],
            'available_at' => (string) $message['available_at'],
            'updated_at' => (string) $message['updated_at'],
            'locked_at' => $message['locked_at'] !== null ? (string) $message['locked_at'] : '',
            'sent_at' => $message['sent_at'] !== null ? (string) $message['sent_at'] : '',
            'last_error' => mb_substr((string) ($message['last_error'] ?? ''), 0, 12000),
            'text_body' => mb_substr((string) ($message['text_body'] ?? ''), 0, 100000),
            'html_body' => mb_substr((string) ($message['html_body'] ?? ''), 0, 100000),
            'timeline' => $timeline,
        ];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public function bulkMail(array $input): array
    {
        $actor = $this->administrator();
        $action = strtolower(trim((string) ($input['action'] ?? '')));
        if (!in_array($action, ['requeue', 'cancel'], true)) {
            throw new InvalidArgumentException('Choose a valid bulk mail action.');
        }
        $rawIds = $input['ids'] ?? [];
        if (!is_array($rawIds)) {
            throw new InvalidArgumentException('Select one or more mail queue items.');
        }
        $ids = [];
        foreach ($rawIds as $rawId) {
            $value = trim((string) $rawId);
            if (!ctype_digit($value) || (int) $value < 1) continue;
            $ids[(int) $value] = (int) $value;
        }
        $ids = array_values($ids);
        if ($ids === []) throw new InvalidArgumentException('Select one or more mail queue items.');
        if (count($ids) > 100) throw new InvalidArgumentException('Bulk mail actions are limited to 100 messages at a time.');

        $repository = new NotificationRepository();
        $pdo = Database::connection();
        $eligible = $action === 'requeue' ? ['failed', 'cancelled'] : ['pending', 'failed'];
        try {
            $pdo->beginTransaction();
            $messages = [];
            foreach ($ids as $id) {
                $message = $repository->outboxMessage($id, true);
                if ($message === null) throw new InvalidArgumentException('One or more selected mail queue items no longer exist.');
                if (!in_array((string) $message['status'], $eligible, true)) {
                    throw new DomainException($action === 'requeue'
                        ? 'Every selected message must be failed or cancelled before bulk requeue.'
                        : 'Every selected message must be pending or failed before bulk cancel.');
                }
                $messages[] = $message;
            }
            foreach ($messages as $message) {
                $messageId = (int) $message['id'];
                $previousStatus = (string) $message['status'];
                if ($action === 'requeue') {
                    $repository->requeueEmail($messageId);
                    $repository->recordOutboxEvent(
                        $messageId,
                        'bulk_requeue',
                        null,
                        (int) $actor['id'],
                        $previousStatus,
                        'pending',
                        null,
                        ['scope' => 'bulk', 'selection_count' => count($ids)]
                    );
                } else {
                    $repository->cancelEmail($messageId);
                    $repository->recordOutboxEvent(
                        $messageId,
                        'bulk_cancel',
                        null,
                        (int) $actor['id'],
                        $previousStatus,
                        'cancelled',
                        null,
                        ['scope' => 'bulk', 'selection_count' => count($ids)]
                    );
                }
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }

        (new SecurityAuditService())->record(
            $action === 'requeue' ? 'admin.communication_mail_bulk_requeued' : 'admin.communication_mail_bulk_cancelled',
            (int) $actor['id'], null,
            ['count' => count($ids), 'email_outbox_ids' => $ids]
        );
        return [
            'action' => $action,
            'affected' => count($ids),
            'message' => count($ids) . ' mail item' . (count($ids) === 1 ? '' : 's') . ($action === 'requeue' ? ' requeued.' : ' cancelled.'),
        ];
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
