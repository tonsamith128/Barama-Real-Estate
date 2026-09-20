<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\CustomerEngagementRepository;
use Barama\Repositories\WorkflowRepository;
use Barama\Support\DomainInput;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class ViewingManagementService
{
    /** @var array<string,array<int,string>> */
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'rescheduled', 'declined'],
        'confirmed' => ['rescheduled', 'completed', 'declined'],
        'rescheduled' => ['confirmed', 'rescheduled', 'completed', 'declined'],
    ];

    /** @param array<string,mixed> $input */
    public function update(int $viewingId, array $input): void
    {
        $repository = new CustomerEngagementRepository();
        $viewing = $repository->viewingById($viewingId);

        if ($viewing === null) {
            throw new InvalidArgumentException('Viewing request was not found.');
        }

        (new CompanyAccessService())->assertCanManage((int) $viewing['company_id']);

        $currentStatus = (string) $viewing['status'];
        $targetStatus = trim((string) ($input['status'] ?? ''));

        if (!in_array($targetStatus, self::TRANSITIONS[$currentStatus] ?? [], true)) {
            throw new DomainException(
                'Viewing request cannot move from '
                . $currentStatus . ' to ' . ($targetStatus !== '' ? $targetStatus : 'an empty status') . '.'
            );
        }

        $scheduledFor = $this->scheduledFor(
            $input['scheduled_for'] ?? null,
            $targetStatus,
            $viewing['scheduled_for'] ?? null
        );
        $note = DomainInput::optionalString($input, 'company_note', 1000);

        $auth = new AuthService();
        $user = $auth->user();
        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            if (!$repository->updateViewingByCompany(
                $viewingId,
                $currentStatus,
                $targetStatus,
                $scheduledFor,
                $note
            )) {
                throw new DomainException(
                    'Viewing request changed while it was being updated. Refresh and try again.'
                );
            }

            (new WorkflowRepository())->record(
                'viewing',
                $viewingId,
                $user !== null ? (int) $user['id'] : null,
                $currentStatus,
                $targetStatus,
                $note
            );

            $statusLabel = ucwords(str_replace('_', ' ', $targetStatus));
            $scheduleText = $scheduledFor !== null
                ? ' Scheduled for ' . date('M j, Y H:i', strtotime($scheduledFor)) . '.'
                : '';
            $message = match ($targetStatus) {
                'confirmed' => 'Your viewing request for ' . (string) $viewing['property_title']
                    . ' has been confirmed.' . $scheduleText,
                'rescheduled' => 'Your viewing request for ' . (string) $viewing['property_title']
                    . ' has been rescheduled.' . $scheduleText,
                'completed' => 'Your viewing for ' . (string) $viewing['property_title']
                    . ' has been marked completed.',
                'declined' => 'Your viewing request for ' . (string) $viewing['property_title']
                    . ' was declined by the company.',
                default => 'Your viewing request status changed to ' . $statusLabel . '.',
            };

            (new NotificationService())->notifyUser(
                (int) $viewing['user_id'],
                'viewing_updates',
                [
                    'type' => 'viewing_' . $targetStatus,
                    'title' => 'Viewing request ' . strtolower($statusLabel),
                    'message' => $message,
                    'action_url' => '/account/viewings',
                    'entity_type' => 'viewing',
                    'entity_id' => $viewingId,
                    'dedupe_key' => 'viewing:' . $viewingId . ':' . $targetStatus . ':'
                        . hash('sha256', (string) $scheduledFor),
                ]
            );

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function scheduledFor(
        mixed $value,
        string $targetStatus,
        mixed $existing
    ): ?string {
        if ($targetStatus === 'declined') {
            return null;
        }

        $requiresSchedule = in_array($targetStatus, ['confirmed', 'rescheduled'], true);
        $raw = trim((string) ($value ?? ''));

        if ($raw === '') {
            if ($requiresSchedule) {
                throw new InvalidArgumentException(
                    'Choose the scheduled viewing date and time.'
                );
            }

            return $existing !== null && trim((string) $existing) !== ''
                ? (string) $existing
                : null;
        }

        $candidate = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $raw);

        if ($candidate === false || $candidate->format('Y-m-d\TH:i') !== $raw) {
            throw new InvalidArgumentException('Scheduled viewing date and time are invalid.');
        }

        if ($requiresSchedule && $candidate < new \DateTimeImmutable('now')) {
            throw new InvalidArgumentException('Scheduled viewing time must be in the future.');
        }

        return $candidate->format('Y-m-d H:i:s');
    }
}
