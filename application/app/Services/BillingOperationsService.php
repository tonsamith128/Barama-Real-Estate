<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\FinanceRepository;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class BillingOperationsService
{
    /** @return array<string,mixed> */
    public function retryWebhookEvent(int $eventId): array
    {
        $actor = $this->administrator();
        $repository = new FinanceRepository();
        $event = $this->actionableEvent($repository, $eventId);

        if ((string) $event['provider_status'] !== 'active') {
            throw new DomainException(
                'Activate the payment provider before retrying this webhook event.'
            );
        }

        $result = (new PaymentWebhookService())->retryStoredEvent($eventId);

        (new SecurityAuditService())->record(
            'admin.billing_webhook_retried',
            (int) $actor['id'],
            null,
            [
                'webhook_event_id' => $eventId,
                'provider' => (string) $event['provider_code'],
                'event_id' => (string) $event['event_id'],
                'result_status' => (string) ($result['status'] ?? ''),
                'transaction_id' => $result['transaction_id'] ?? null,
            ]
        );

        return $result;
    }

    public function ignoreWebhookEvent(int $eventId): void
    {
        $actor = $this->administrator();
        $repository = new FinanceRepository();
        $event = $this->actionableEvent($repository, $eventId);

        $repository->completeWebhookEvent(
            $eventId,
            'ignored',
            null,
            null
        );

        (new SecurityAuditService())->record(
            'admin.billing_webhook_ignored',
            (int) $actor['id'],
            null,
            [
                'webhook_event_id' => $eventId,
                'provider' => (string) $event['provider_code'],
                'event_id' => (string) $event['event_id'],
                'previous_status' => (string) $event['status'],
            ]
        );
    }

    /** @return array<string,mixed> */
    private function actionableEvent(
        FinanceRepository $repository,
        int $eventId
    ): array {
        if ($eventId < 1) {
            throw new InvalidArgumentException(
                'A valid webhook event ID is required.'
            );
        }

        $event = $repository->webhookEventById($eventId);

        if ($event === null) {
            throw new InvalidArgumentException('Webhook event was not found.');
        }

        $status = (string) $event['status'];

        if ($status === 'failed') {
            return $event;
        }

        if ($status === 'received') {
            $receivedAt = new DateTimeImmutable((string) $event['received_at']);
            $staleBefore = new DateTimeImmutable('-10 minutes');

            if ($receivedAt <= $staleBefore) {
                return $event;
            }

            throw new DomainException(
                'This webhook event is still within the active processing window.'
            );
        }

        throw new DomainException(
            'Only failed or stale unprocessed webhook events can be managed.'
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
            || !$auth->can('billing.manage')
        ) {
            throw new DomainException(
                'Administrator billing permission is required.'
            );
        }

        return $user;
    }
}
