<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\FinanceRepository;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PDOException;
use Throwable;

final class PaymentWebhookService
{
    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function receive(
        string $providerCode,
        array $payload,
        string $token
    ): array {
        $providerCode = strtolower(trim($providerCode));
        $token = trim($token);
        $repository = new FinanceRepository();

        if (
            $providerCode === ''
            || strlen($providerCode) > 24
            || preg_match('/^[a-z0-9][a-z0-9_-]*$/', $providerCode) !== 1
        ) {
            throw new DomainException('Webhook authentication failed.', 401);
        }

        $provider = $repository->paymentProviderByCode($providerCode);

        if (
            $provider === null
            || (string) $provider['status'] !== 'active'
            || (string) $provider['adapter_key'] !== 'generic_token'
            || $token === ''
            || !password_verify($token, (string) $provider['webhook_token_hash'])
        ) {
            throw new DomainException('Webhook authentication failed.', 401);
        }

        $eventId = trim((string) ($payload['event_id'] ?? ''));
        $eventType = strtolower(trim((string) ($payload['event_type'] ?? $payload['type'] ?? '')));

        if ($eventId === '' || mb_strlen($eventId) > 191) {
            throw new InvalidArgumentException(
                'Webhook event_id is required and cannot exceed 191 characters.'
            );
        }

        if (
            $eventType === ''
            || mb_strlen($eventType) > 96
            || preg_match('/^[a-z0-9._-]+$/', $eventType) !== 1
        ) {
            throw new InvalidArgumentException(
                'Webhook event_type is required and contains unsupported characters.'
            );
        }

        $payloadJson = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        if (strlen($payloadJson) > 262144) {
            throw new InvalidArgumentException(
                'Webhook payload cannot exceed 256 KB in this foundation.'
            );
        }

        $payloadHash = hash('sha256', $payloadJson);
        $providerId = (int) $provider['id'];
        $event = $repository->webhookEvent($providerId, $eventId);

        if ($event === null) {
            try {
                $repository->createWebhookEvent([
                    'provider_id' => $providerId,
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                    'payload_json' => $payloadJson,
                    'payload_sha256' => $payloadHash,
                ]);
            } catch (PDOException $exception) {
                if ((string) $exception->getCode() !== '23000') {
                    throw $exception;
                }
            }

            $event = $repository->webhookEvent($providerId, $eventId);
        }

        if ($event === null) {
            throw new DomainException('Webhook receipt could not be persisted.', 500);
        }

        if (!hash_equals((string) $event['payload_sha256'], $payloadHash)) {
            throw new DomainException(
                'A webhook event with this event_id was already received with different content.',
                409
            );
        }

        if (in_array((string) $event['status'], ['processed', 'ignored'], true)) {
            return [
                'provider' => $providerCode,
                'event_id' => $eventId,
                'status' => (string) $event['status'],
                'duplicate' => true,
                'transaction_id' => $event['transaction_id'] !== null
                    ? (int) $event['transaction_id']
                    : null,
            ];
        }

        return $this->process(
            $provider,
            $eventId,
            $eventType,
            $payload,
            $payloadHash
        );
    }

    /** @return array<string,mixed> */
    public function retryStoredEvent(int $eventId): array
    {
        if ($eventId < 1) {
            throw new InvalidArgumentException('A valid webhook event ID is required.');
        }

        $repository = new FinanceRepository();
        $event = $repository->webhookEventById($eventId);

        if ($event === null) {
            throw new InvalidArgumentException('Webhook event was not found.');
        }

        if (!in_array((string) $event['status'], ['failed', 'received'], true)) {
            throw new DomainException(
                'Only failed or unprocessed webhook events can be retried.'
            );
        }

        if ((string) $event['provider_status'] !== 'active') {
            throw new DomainException(
                'The payment provider must be active before retrying this event.'
            );
        }

        if ((string) $event['adapter_key'] !== 'generic_token') {
            throw new DomainException(
                'This webhook event requires a provider-specific adapter.'
            );
        }

        $payloadJson = (string) $event['payload_json'];

        if (!hash_equals(
            (string) $event['payload_sha256'],
            hash('sha256', $payloadJson)
        )) {
            throw new DomainException(
                'Stored webhook payload integrity verification failed.'
            );
        }

        $payload = json_decode(
            $payloadJson,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($payload)) {
            throw new DomainException('Stored webhook payload is invalid.');
        }

        return $this->process(
            [
                'id' => (int) $event['provider_id'],
                'code' => (string) $event['provider_code'],
            ],
            (string) $event['event_id'],
            (string) $event['event_type'],
            $payload,
            (string) $event['payload_sha256']
        );
    }

    /**
     * @param array<string,mixed> $provider
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function process(
        array $provider,
        string $eventId,
        string $eventType,
        array $payload,
        string $payloadHash
    ): array {
        $repository = new FinanceRepository();
        $providerId = (int) $provider['id'];
        $providerCode = (string) $provider['code'];
        $pdo = Database::connection();
        $eventRowId = null;

        $eventMap = [
            'payment.succeeded' => ['payment', 'succeeded'],
            'payment.failed' => ['payment', 'failed'],
            'refund.succeeded' => ['refund', 'succeeded'],
            'refund.failed' => ['refund', 'failed'],
            'credit.succeeded' => ['credit', 'succeeded'],
            'credit.failed' => ['credit', 'failed'],
        ];

        try {
            $pdo->beginTransaction();

            $event = $repository->lockWebhookEvent($providerId, $eventId);

            if ($event === null) {
                throw new DomainException('Webhook receipt disappeared during processing.', 500);
            }

            $eventRowId = (int) $event['id'];

            if (!hash_equals((string) $event['payload_sha256'], $payloadHash)) {
                throw new DomainException(
                    'Webhook event content changed during retry.',
                    409
                );
            }

            if (in_array((string) $event['status'], ['processed', 'ignored'], true)) {
                $pdo->commit();

                return [
                    'provider' => $providerCode,
                    'event_id' => $eventId,
                    'status' => (string) $event['status'],
                    'duplicate' => true,
                    'transaction_id' => $event['transaction_id'] !== null
                        ? (int) $event['transaction_id']
                        : null,
                ];
            }

            if (!isset($eventMap[$eventType])) {
                $repository->completeWebhookEvent($eventRowId, 'ignored');
                $pdo->commit();

                (new SecurityAuditService())->record(
                    'billing.webhook_ignored',
                    null,
                    null,
                    [
                        'provider' => $providerCode,
                        'event_id' => $eventId,
                        'event_type' => $eventType,
                    ]
                );

                return [
                    'provider' => $providerCode,
                    'event_id' => $eventId,
                    'status' => 'ignored',
                    'duplicate' => false,
                    'transaction_id' => null,
                ];
            }

            [$transactionType, $transactionStatus] = $eventMap[$eventType];

            $invoiceNumber = strtoupper(trim((string) ($payload['invoice_number'] ?? '')));

            if ($invoiceNumber === '' || mb_strlen($invoiceNumber) > 64) {
                throw new InvalidArgumentException(
                    'Supported financial webhook events require invoice_number.'
                );
            }

            $invoice = $repository->invoiceByNumber($invoiceNumber);

            if ($invoice === null) {
                throw new InvalidArgumentException('Webhook invoice was not found.');
            }

            if ((string) $invoice['status'] === 'void') {
                throw new DomainException(
                    'Webhook transactions cannot be linked to a void invoice.',
                    409
                );
            }

            $amount = $this->decimal($payload['amount'] ?? '', 'Webhook amount');

            if ((float) $amount <= 0) {
                throw new InvalidArgumentException(
                    'Webhook amount must be greater than zero.'
                );
            }

            $currency = strtoupper(trim((string) ($payload['currency'] ?? '')));

            if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                throw new InvalidArgumentException(
                    'Webhook currency must use a three-letter ISO code.'
                );
            }

            if (strtoupper((string) $invoice['currency']) !== $currency) {
                throw new DomainException(
                    'Webhook currency does not match the invoice currency.',
                    409
                );
            }

            if (
                $transactionType === 'refund'
                && $transactionStatus === 'succeeded'
                && $repository->invoiceSettledAmount((int) $invoice['id']) + 0.00001
                    < (float) $amount
            ) {
                throw new DomainException(
                    'Successful webhook refund exceeds the invoice settled amount.',
                    409
                );
            }

            $reference = trim((string) ($payload['reference'] ?? $eventId));

            if ($reference === '' || mb_strlen($reference) > 191) {
                throw new InvalidArgumentException(
                    'Webhook reference cannot exceed 191 characters.'
                );
            }

            $processedAt = $this->webhookDateTime($payload['processed_at'] ?? null);

            $transactionId = $repository->createTransaction([
                'company_id' => (int) $invoice['company_id'],
                'subscription_id' => $invoice['subscription_id'] !== null
                    ? (int) $invoice['subscription_id']
                    : null,
                'invoice_id' => (int) $invoice['id'],
                'transaction_type' => $transactionType,
                'status' => $transactionStatus,
                'source' => 'webhook:' . $providerCode,
                'reference' => $reference,
                'amount' => $amount,
                'currency' => $currency,
                'processed_at' => $processedAt->format('Y-m-d H:i:s'),
                'notes' => 'Webhook event ' . $eventType,
                'actor_id' => null,
            ]);

            $repository->completeWebhookEvent(
                $eventRowId,
                'processed',
                $transactionId
            );

            $pdo->commit();

            (new SecurityAuditService())->record(
                'billing.webhook_processed',
                null,
                null,
                [
                    'provider' => $providerCode,
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                    'transaction_id' => $transactionId,
                    'invoice_id' => (int) $invoice['id'],
                ]
            );

            return [
                'provider' => $providerCode,
                'event_id' => $eventId,
                'status' => 'processed',
                'duplicate' => false,
                'transaction_id' => $transactionId,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($eventRowId !== null) {
                $repository->completeWebhookEvent(
                    $eventRowId,
                    'failed',
                    null,
                    mb_substr($exception->getMessage(), 0, 500)
                );
            }

            (new SecurityAuditService())->record(
                'billing.webhook_failed',
                null,
                null,
                [
                    'provider' => $providerCode,
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                    'error' => mb_substr($exception->getMessage(), 0, 300),
                ]
            );

            throw $exception;
        }
    }

    private function decimal(mixed $value, string $label): string
    {
        $value = trim((string) $value);

        if ($value === '' || !preg_match('/^\d{1,15}(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException(
                $label . ' must be a valid non-negative amount.'
            );
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function webhookDateTime(mixed $value): DateTimeImmutable
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return new DateTimeImmutable();
        }

        if (mb_strlen($value) > 64) {
            throw new InvalidArgumentException('Webhook processed_at is invalid.');
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            throw new InvalidArgumentException('Webhook processed_at is invalid.');
        }
    }
}
