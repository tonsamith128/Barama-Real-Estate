<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Domain\RealEstate\DomainRules;
use Barama\Repositories\InquiryRepository;
use Barama\Repositories\RoleRepository;
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

        if (
            $isAdmin
            && $resource === 'project'
            && in_array($toStatus, ['approved', 'published'], true)
        ) {
            if ((string) ($context['company_status'] ?? '') !== 'approved') {
                throw new DomainException(
                    $toStatus === 'published'
                        ? 'A project cannot be published unless its company is currently approved.'
                        : 'A project cannot be approved until its company is approved.'
                );
            }
        }

        if ($isAdmin && $resource === 'property' && $toStatus === 'approved') {
            if ((string) ($context['company_status'] ?? '') !== 'approved') {
                throw new DomainException(
                    'A property cannot be approved until its company is approved.'
                );
            }

            if (
                !empty($context['project_id'])
                && !in_array(
                    (string) ($context['project_status'] ?? ''),
                    ['approved', 'published'],
                    true
                )
            ) {
                throw new DomainException(
                    'A property linked to a project cannot be approved until that project is approved.'
                );
            }
        }

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
            if ((string) ($context['property_status'] ?? '') !== 'approved') {
                throw new DomainException(
                    'A listing cannot be published until its property is approved.'
                );
            }

            if ((string) ($context['company_status'] ?? '') !== 'approved') {
                throw new DomainException(
                    'A listing cannot be published unless its company is currently approved.'
                );
            }

            if (
                !empty($context['project_id'])
                && !in_array(
                    (string) ($context['project_status'] ?? ''),
                    ['approved', 'published'],
                    true
                )
            ) {
                throw new DomainException(
                    'A listing cannot be published while its linked project is not approved.'
                );
            }
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();

            $repository->updateStatus($resource, $entityId, $toStatus);

            if ($resource === 'company' && $toStatus === 'approved') {
                $memberStatement = $pdo->prepare(
                    'SELECT u.id, u.email, u.name
                     FROM company_members cm
                     INNER JOIN users u ON u.id = cm.user_id
                     WHERE cm.company_id = :company_id
                       AND cm.status = "active"
                       AND u.status = "active"'
                );
                $memberStatement->execute(['company_id' => $entityId]);
                $roles = new RoleRepository();

                foreach ($memberStatement->fetchAll(\PDO::FETCH_ASSOC) as $member) {
                    $memberId = (int) $member['id'];

                    if (!$roles->userHasRole($memberId, 'developer')) {
                        $roles->assignRole($memberId, 'developer');
                    }
                }
            }

            $repository->record(
                $resource,
                $entityId,
                (int) $user['id'],
                $fromStatus,
                $toStatus,
                $note
            );

            if ($resource === 'company' && $toStatus === 'approved') {
                $companyMembers = $pdo->prepare(
                    'SELECT u.email
                     FROM company_members cm
                     INNER JOIN users u ON u.id = cm.user_id
                     WHERE cm.company_id = :company_id
                       AND cm.member_role = "owner"
                       AND cm.status = "active"
                       AND u.status = "active"'
                );
                $companyMembers->execute(['company_id' => $entityId]);

                foreach ($companyMembers->fetchAll(\PDO::FETCH_COLUMN) as $ownerEmail) {
                    if (is_string($ownerEmail) && filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
                        (new NotificationService())->notifyAddress($ownerEmail, [
                            'subject' => 'Company approved · Barama Real Estate',
                            'html' => '<p><strong>Your company has been approved.</strong></p>'
                                . '<p>Company / Vendor Workspace access is now active for your account.</p>'
                                . '<p><a href="' . e(url('/developer')) . '">Open company workspace</a></p>',
                            'text' => "Your company has been approved.\n\nOpen company workspace: "
                                . url('/developer'),
                            'dedupe_key' => 'company-approved:' . $entityId,
                        ]);
                    }
                }
            }

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
