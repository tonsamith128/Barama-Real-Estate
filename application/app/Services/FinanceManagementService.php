<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Core\Database;
use Barama\Repositories\FinanceRepository;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Throwable;

final class FinanceManagementService
{
    /** @param array<string,mixed> $input */
    public function createPlan(array $input): int
    {
        $actor = $this->administrator();
        $repository = new FinanceRepository();

        $name = trim((string) ($input['name'] ?? ''));
        $slug = strtolower(trim((string) ($input['slug'] ?? '')));
        $description = trim((string) ($input['description'] ?? ''));
        $interval = strtolower(trim((string) ($input['billing_interval'] ?? 'monthly')));
        $currency = strtoupper(trim((string) ($input['currency'] ?? 'USD')));
        $price = $this->decimal($input['price'] ?? '0', 'Plan price');

        if (mb_strlen($name) < 2 || mb_strlen($name) > 160) {
            throw new InvalidArgumentException('Plan name must contain between 2 and 160 characters.');
        }

        if ($slug === '') {
            $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) ?? '';
            $slug = trim($slug, '-');
        }

        if (
            $slug === ''
            || mb_strlen($slug) > 160
            || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1
        ) {
            throw new InvalidArgumentException('Plan slug is invalid.');
        }

        if ($repository->planSlugExists($slug)) {
            throw new InvalidArgumentException('This plan slug is already in use.');
        }

        if (mb_strlen($description) > 5000) {
            throw new InvalidArgumentException('Plan description cannot exceed 5,000 characters.');
        }

        if (!in_array($interval, ['monthly', 'quarterly', 'yearly', 'lifetime', 'manual'], true)) {
            throw new InvalidArgumentException('Choose a supported billing interval.');
        }

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('Currency must use a three-letter ISO code.');
        }

        $data = [
            'name' => $name,
            'slug' => $slug,
            'description' => $description !== '' ? $description : null,
            'billing_interval' => $interval,
            'price' => $price,
            'currency' => $currency,
            'listing_limit' => $this->nullableLimit($input['listing_limit'] ?? null, 'Listing limit'),
            'property_limit' => $this->nullableLimit($input['property_limit'] ?? null, 'Property limit'),
            'project_limit' => $this->nullableLimit($input['project_limit'] ?? null, 'Project limit'),
            'team_member_limit' => $this->nullableLimit($input['team_member_limit'] ?? null, 'Team member limit'),
            'featured_listing_limit' => $this->nullableLimit($input['featured_listing_limit'] ?? null, 'Featured listing limit'),
            'sort_order' => $this->integer($input['sort_order'] ?? 0, 'Sort order', -10000, 10000),
        ];

        $id = $repository->createPlan($data);

        (new SecurityAuditService())->record(
            'admin.billing_plan_created',
            (int) $actor['id'],
            null,
            ['plan_id' => $id, 'slug' => $slug]
        );

        return $id;
    }

    public function setPlanStatus(int $id, string $status): void
    {
        $actor = $this->administrator();
        $repository = new FinanceRepository();
        $plan = $repository->plan($id);

        if ($plan === null) {
            throw new InvalidArgumentException('Subscription plan was not found.');
        }

        $status = strtolower(trim($status));

        if (!in_array($status, ['active', 'inactive', 'archived'], true)) {
            throw new InvalidArgumentException('Unsupported plan status.');
        }

        if ($status === 'archived' && (string) $plan['status'] === 'active') {
            throw new DomainException('Deactivate the plan before archiving it.');
        }

        $repository->setPlanStatus($id, $status);

        (new SecurityAuditService())->record(
            'admin.billing_plan_status_changed',
            (int) $actor['id'],
            null,
            [
                'plan_id' => $id,
                'from_status' => (string) $plan['status'],
                'to_status' => $status,
            ]
        );
    }

    /** @param array<string,mixed> $input */
    public function assignSubscription(array $input): int
    {
        $actor = $this->administrator();
        $repository = new FinanceRepository();

        $companyId = $this->positiveId($input['company_id'] ?? null, 'Company');
        $planId = $this->positiveId($input['plan_id'] ?? null, 'Plan');

        if (!$repository->companyExists($companyId)) {
            throw new InvalidArgumentException('Company is unavailable for subscription assignment.');
        }

        $plan = $repository->plan($planId);

        if ($plan === null || (string) $plan['status'] !== 'active') {
            throw new InvalidArgumentException('Choose an active subscription plan.');
        }

        if ($repository->hasCurrentSubscription($companyId)) {
            throw new DomainException(
                'This company already has a current subscription. End it before assigning another plan.'
            );
        }

        $startsAt = $this->dateTime($input['starts_at'] ?? null, 'Start date') ?? new DateTimeImmutable();
        $renewsAt = $this->dateTime($input['renews_at'] ?? null, 'Renewal date');
        $expiresAt = $this->dateTime($input['expires_at'] ?? null, 'Expiry date');

        if ($renewsAt !== null && $renewsAt <= $startsAt) {
            throw new InvalidArgumentException('Renewal date must be after the subscription start.');
        }

        if ($expiresAt !== null && $expiresAt <= $startsAt) {
            throw new InvalidArgumentException('Expiry date must be after the subscription start.');
        }

        $externalReference = trim((string) ($input['external_reference'] ?? ''));
        $notes = trim((string) ($input['notes'] ?? ''));

        if (mb_strlen($externalReference) > 191) {
            throw new InvalidArgumentException('External reference cannot exceed 191 characters.');
        }

        if (mb_strlen($notes) > 5000) {
            throw new InvalidArgumentException('Subscription notes cannot exceed 5,000 characters.');
        }

        $pdo = Database::connection();

        try {
            $pdo->beginTransaction();
            $repository->lockCompany($companyId);

            if ($repository->hasCurrentSubscription($companyId)) {
                throw new DomainException('A current subscription was created concurrently for this company.');
            }

            $id = $repository->createSubscription([
                'company_id' => $companyId,
                'plan_id' => $planId,
                'external_reference' => $externalReference !== '' ? $externalReference : null,
                'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                'renews_at' => $renewsAt?->format('Y-m-d H:i:s'),
                'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
                'notes' => $notes !== '' ? $notes : null,
                'actor_id' => (int) $actor['id'],
            ]);

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        (new SecurityAuditService())->record(
            'admin.company_subscription_assigned',
            (int) $actor['id'],
            null,
            [
                'subscription_id' => $id,
                'company_id' => $companyId,
                'plan_id' => $planId,
            ]
        );

        return $id;
    }

    public function setSubscriptionStatus(int $id, string $status): void
    {
        $actor = $this->administrator();
        $repository = new FinanceRepository();
        $subscription = $repository->subscription($id);

        if ($subscription === null) {
            throw new InvalidArgumentException('Company subscription was not found.');
        }

        $status = strtolower(trim($status));
        $current = (string) $subscription['status'];

        $transitions = [
            'active' => ['paused', 'cancelled', 'expired'],
            'paused' => ['active', 'cancelled', 'expired'],
            'cancelled' => [],
            'expired' => [],
        ];

        if (!in_array($status, $transitions[$current] ?? [], true)) {
            throw new DomainException('This subscription status transition is not allowed.');
        }

        if (
            $status === 'active'
            && $repository->hasCurrentSubscription((int) $subscription['company_id'])
            && $current !== 'paused'
        ) {
            throw new DomainException('The company already has a current subscription.');
        }

        $repository->setSubscriptionStatus($id, $status, (int) $actor['id']);

        (new SecurityAuditService())->record(
            'admin.company_subscription_status_changed',
            (int) $actor['id'],
            null,
            [
                'subscription_id' => $id,
                'company_id' => (int) $subscription['company_id'],
                'from_status' => $current,
                'to_status' => $status,
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
            || !$auth->can('billing.manage')
        ) {
            throw new DomainException('Administrator billing permission is required.');
        }

        return $user;
    }

    private function positiveId(mixed $value, string $label): int
    {
        $validated = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($validated === false) {
            throw new InvalidArgumentException($label . ' is required.');
        }

        return (int) $validated;
    }

    private function nullableLimit(mixed $value, string $label): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $this->integer($value, $label, 0, 1000000);
    }

    private function integer(mixed $value, string $label, int $min, int $max): int
    {
        $validated = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => $min, 'max_range' => $max]]
        );

        if ($validated === false) {
            throw new InvalidArgumentException(
                sprintf('%s must be between %d and %d.', $label, $min, $max)
            );
        }

        return (int) $validated;
    }

    private function decimal(mixed $value, string $label): string
    {
        $value = trim((string) $value);

        if ($value === '' || !preg_match('/^\d{1,15}(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException($label . ' must be a valid non-negative amount.');
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function dateTime(mixed $value, string $label): ?DateTimeImmutable
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);

        if (!$date instanceof DateTimeImmutable) {
            throw new InvalidArgumentException($label . ' is invalid.');
        }

        return $date;
    }
}
