<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Domain\RealEstate\DomainRules;
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

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
}
