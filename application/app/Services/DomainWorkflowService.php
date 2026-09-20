<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Domain\RealEstate\DomainRules;
use Barama\Repositories\InquiryRepository;
use Barama\Repositories\ListingWriteRepository;
use Barama\Repositories\WorkflowRepository;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class DomainWorkflowService
{
    private const PERMISSIONS = [
        'company' => 'company.manage',
        'project' => 'project.manage',
        'property' => 'property.manage',
        'listing' => 'listing.manage',
        'inquiry' => 'inquiry.manage',
        'lead' => 'lead.manage',
    ];

    private const DEVELOPER_TARGETS = [
        'company' => ['draft', 'pending'],
        'project' => ['draft', 'pending'],
        'property' => ['draft', 'pending'],
        'listing' => ['draft', 'pending', 'published', 'paused', 'closed'],
        'inquiry' => ['read', 'replied', 'closed', 'spam'],
        'lead' => ['contacted', 'qualified', 'viewing', 'negotiation', 'won', 'lost', 'new'],
    ];

    public function transition(
        string $resource,
        int $entityId,
        string $toStatus,
        ?string $note = null
    ): void {
        if (!isset(self::PERMISSIONS[$resource])) {
            throw new InvalidArgumentException('Unsupported workflow resource.');
        }

        $auth = new AuthService();
        $user = $auth->user();

        if ($user === null || !$auth->can(self::PERMISSIONS[$resource])) {
            throw new DomainException('Workflow permission is required.');
        }

        $repository = new WorkflowRepository();
        $context = $repository->context($resource, $entityId);

        if ($context === null) {
            throw new InvalidArgumentException('Workflow entity was not found.');
        }

        $fromStatus = (string) $context['status'];
        DomainRules::assertStatus($resource, $fromStatus);
        DomainRules::assertStatus($resource, $toStatus);

        if (!DomainRules::canTransition($resource, $fromStatus, $toStatus)) {
            throw new DomainException(
                "Status transition {$fromStatus} -> {$toStatus} is not allowed."
            );
        }

        $isAdmin = $auth->hasRole('administrator');

        if (!$isAdmin) {
            if (!in_array($toStatus, self::DEVELOPER_TARGETS[$resource], true)) {
                throw new DomainException('This status transition requires administrator review.');
            }

            $companyId = $context['company_id'] ?? null;

            if ($companyId === null) {
                throw new DomainException('The resource is not assigned to a manageable company.');
            }

            (new CompanyAccessService())->assertCanManage((int) $companyId);

            if (
                in_array($resource, ['company', 'project', 'property'], true)
                && !in_array($toStatus, ['draft', 'pending'], true)
            ) {
                throw new DomainException('Approval transitions require an administrator.');
            }

            if (
                $resource === 'listing'
                && $toStatus === 'published'
                && $fromStatus !== 'paused'
            ) {
                throw new DomainException('Initial listing publication requires administrator approval.');
            }
        }

        if ($resource === 'listing' && $toStatus === 'published') {
            $listing = (new ListingWriteRepository())->findById($entityId);

            if ($listing === null || (string) $listing['property_status'] !== 'approved') {
                throw new DomainException('A listing cannot be published until its property is approved.');
            }
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $repository->updateStatus($resource, $entityId, $toStatus);
            $repository->record(
                $resource,
                $entityId,
                (int) $user['id'],
                $fromStatus,
                $toStatus,
                $note
            );

            if ($resource === 'inquiry' && in_array($toStatus, ['replied', 'closed'], true)) {
                $inquiry = (new InquiryRepository())->findById($entityId);

                if ($inquiry !== null) {
                    $notificationData = [
                        'type' => 'inquiry_' . $toStatus,
                        'title' => $toStatus === 'replied'
                            ? 'Your inquiry was replied to'
                            : 'Your inquiry was closed',
                        'message' => $toStatus === 'replied'
                            ? 'The company has updated your property inquiry.'
                            : 'Your property inquiry has been closed.',
                        'action_url' => '/account/inquiries',
                        'entity_type' => 'inquiry',
                        'entity_id' => $entityId,
                        'dedupe_key' => 'inquiry:' . $entityId . ':' . $toStatus,
                    ];

                    if (!empty($inquiry['user_id'])) {
                        (new NotificationService())->notifyUser(
                            (int) $inquiry['user_id'],
                            'inquiry_updates',
                            $notificationData
                        );
                    } elseif (!empty($inquiry['contact_email'])) {
                        $subject = (string) $notificationData['title'] . ' · Barama Real Estate';
                        $message = (string) $notificationData['message'];

                        (new NotificationService())->notifyAddress(
                            (string) $inquiry['contact_email'],
                            [
                                'subject' => $subject,
                                'html' => '<p><strong>' . e((string) $notificationData['title'])
                                    . '</strong></p><p>' . e($message) . '</p>',
                                'text' => (string) $notificationData['title'] . "\n\n" . $message,
                                'dedupe_key' => (string) $notificationData['dedupe_key'],
                            ]
                        );
                    }
                }
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
}
