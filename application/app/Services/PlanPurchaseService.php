<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\FinanceRepository;
use Barama\Support\IdGenerator;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class PlanPurchaseService
{
    /** @return array{id:int,reference:string,company_id:int,plan_id:int} */
    public function request(int $userId, int $planId, int $companyId): array
    {
        if ($userId < 1 || $planId < 1 || $companyId < 1) {
            throw new InvalidArgumentException('A valid plan and company are required.');
        }

        $repository = new FinanceRepository();
        $plan = $repository->plan($planId);

        if ($plan === null || (string) ($plan['status'] ?? '') !== 'active') {
            throw new InvalidArgumentException('This plan is not currently available for purchase.');
        }

        $allowed = false;
        foreach ($repository->developerBillingCompanies($userId) as $company) {
            if ((int) ($company['id'] ?? 0) === $companyId) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            throw new DomainException(
                'Choose a company that belongs to your Company / Vendor workspace.'
            );
        }

        $pdo = Database::connection();
        $reference = IdGenerator::reference('PLAN');

        try {
            $pdo->beginTransaction();

            $lock = $pdo->prepare(
                'SELECT id FROM companies WHERE id = :id LIMIT 1 FOR UPDATE'
            );
            $lock->execute(['id' => $companyId]);

            if ($lock->fetchColumn() === false) {
                throw new InvalidArgumentException('The selected company is unavailable.');
            }

            $existing = $pdo->prepare(
                'SELECT id, status, plan_id, external_reference
                 FROM company_subscriptions
                 WHERE company_id = :company_id
                   AND status IN ("active", "paused", "pending")
                 ORDER BY id DESC
                 LIMIT 1
                 FOR UPDATE'
            );
            $existing->execute(['company_id' => $companyId]);
            $current = $existing->fetch();

            if (is_array($current)) {
                if ((string) ($current['status'] ?? '') === 'pending') {
                    throw new DomainException(
                        'A plan purchase request is already awaiting billing review for this company.'
                    );
                }

                throw new DomainException(
                    'This company already has a current subscription. Manage the existing subscription before purchasing another plan.'
                );
            }

            $insert = $pdo->prepare(
                'INSERT INTO company_subscriptions
                    (uuid, company_id, plan_id, status, source,
                     external_reference, starts_at, notes,
                     created_by_user_id, updated_by_user_id)
                 VALUES
                    (:uuid, :company_id, :plan_id, "pending", "self_service",
                     :external_reference, NOW(), :notes,
                     :user_id, :user_id)'
            );
            $insert->execute([
                'uuid' => IdGenerator::uuid(),
                'company_id' => $companyId,
                'plan_id' => $planId,
                'external_reference' => $reference,
                'notes' => 'Self-service plan purchase request. Awaiting payment confirmation and administrator activation.',
                'user_id' => $userId,
            ]);

            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        (new SecurityAuditService())->record(
            'customer.plan_purchase_requested',
            $userId,
            null,
            [
                'subscription_id' => $id,
                'company_id' => $companyId,
                'plan_id' => $planId,
                'reference' => $reference,
                'source' => 'self_service',
            ]
        );

        return [
            'id' => $id,
            'reference' => $reference,
            'company_id' => $companyId,
            'plan_id' => $planId,
        ];
    }
}
