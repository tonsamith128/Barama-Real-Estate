<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\NotificationRepository;
use Throwable;

final class OutboxDeliveryService
{
    /** @return array{processed:int,sent:int,failed:int} */
    public function deliver(int $limit = 50): array
    {
        $repository = new NotificationRepository();
        $maxAttempts = max(1, (int) config('mail.max_attempts', 5));
        $staleMinutes = max(5, (int) config('mail.processing_stale_minutes', 15));
        $retryMinutes = max(1, (int) config('mail.retry_minutes', 10));
        $mailer = new MailService();

        $result = ['processed' => 0, 'sent' => 0, 'failed' => 0];

        foreach (
            $repository->outboxCandidates(
                max(1, min(500, $limit)),
                $maxAttempts,
                $staleMinutes
            ) as $candidate
        ) {
            $email = $repository->claimEmail(
                (int) $candidate['id'],
                $maxAttempts,
                $staleMinutes
            );

            if ($email === null) {
                continue;
            }

            $result['processed']++;

            try {
                $sent = $mailer->send(
                    (string) $email['to_address'],
                    (string) $email['subject'],
                    (string) $email['html_body'],
                    (string) $email['text_body']
                );

                if ($sent) {
                    $repository->markEmailSent((int) $email['id']);
                    $result['sent']++;
                    continue;
                }

                $attempts = max(1, (int) $email['attempts']);
                $delay = $retryMinutes * min(6, $attempts);
                $repository->markEmailFailed(
                    (int) $email['id'],
                    $mailer->lastError() ?? 'Mail transport returned false.',
                    date('Y-m-d H:i:s', time() + ($delay * 60))
                );
                $result['failed']++;
            } catch (Throwable $exception) {
                $attempts = max(1, (int) $email['attempts']);
                $delay = $retryMinutes * min(6, $attempts);
                $repository->markEmailFailed(
                    (int) $email['id'],
                    $exception->getMessage(),
                    date('Y-m-d H:i:s', time() + ($delay * 60))
                );
                $result['failed']++;
            }
        }

        return $result;
    }
}
