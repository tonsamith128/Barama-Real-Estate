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

    /** @param array<string,mixed> $input */
    public function updatePlan(int $id, array $input): void
    {
        $actor = $this->administrator();
        $repository = new FinanceRepository();
        $plan = $repository->plan($id);

        if ($plan === null) {
            throw new InvalidArgumentException(
                'Subscription plan was not found.'
            );
        }

        if ((string) ($plan['status'] ?? '') === 'archived') {
            throw new DomainException(
                'Archived plans are read-only.'
            );
        }

        $name = trim((string) ($input['name'] ?? ''));
        $slug = strtolower(trim((string) ($input['slug'] ?? '')));
        $description = trim((string) ($input['description'] ?? ''));
        $interval = strtolower(
            trim((string) ($input['billing_interval'] ?? 'monthly'))
        );
        $currency = strtoupper(
            trim((string) ($input['currency'] ?? 'USD'))
        );
        $price = $this->decimal(
            $input['price'] ?? '0',
            'Plan price'
        );

        if (mb_strlen($name) < 2 || mb_strlen($name) > 160) {
            throw new InvalidArgumentException(
                'Plan name must contain between 2 and 160 characters.'
            );
        }

        if ($slug === '') {
            $slug = preg_replace(
                '/[^a-z0-9]+/',
                '-',
                strtolower($name)
            ) ?? '';
            $slug = trim($slug, '-');
        }

        if (
            $slug === ''
            || mb_strlen($slug) > 160
            || preg_match(
                '/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $slug
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Plan slug is invalid.'
            );
        }

        if (
            $slug !== (string) ($plan['slug'] ?? '')
            && $repository->planSlugExists($slug)
        ) {
            throw new InvalidArgumentException(
                'This plan slug is already in use.'
            );
        }

        if (mb_strlen($description) > 5000) {
            throw new InvalidArgumentException(
                'Plan description cannot exceed 5,000 characters.'
            );
        }

        if (
            !in_array(
                $interval,
                [
                    'monthly',
                    'quarterly',
                    'yearly',
                    'lifetime',
                    'manual',
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Choose a supported billing interval.'
            );
        }

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException(
                'Currency must use a three-letter ISO code.'
            );
        }

        $repository->updatePlan($id, [
            'name' => $name,
            'slug' => $slug,
            'description' => $description !== ''
                ? $description
                : null,
            'billing_interval' => $interval,
            'price' => $price,
            'currency' => $currency,
            'listing_limit' => $this->nullableLimit(
                $input['listing_limit'] ?? null,
                'Listing limit'
            ),
            'property_limit' => $this->nullableLimit(
                $input['property_limit'] ?? null,
                'Property limit'
            ),
            'project_limit' => $this->nullableLimit(
                $input['project_limit'] ?? null,
                'Project limit'
            ),
            'team_member_limit' => $this->nullableLimit(
                $input['team_member_limit'] ?? null,
                'Team member limit'
            ),
            'featured_listing_limit' => $this->nullableLimit(
                $input['featured_listing_limit'] ?? null,
                'Featured listing limit'
            ),
            'sort_order' => $this->integer(
                $input['sort_order'] ?? 0,
                'Sort order',
                -10000,
                10000
            ),
        ]);

        (new SecurityAuditService())->record(
            'admin.billing_plan_updated',
            (int) $actor['id'],
            null,
            [
                'plan_id' => $id,
                'from_slug' => (string) ($plan['slug'] ?? ''),
                'to_slug' => $slug,
                'status' => (string) ($plan['status'] ?? ''),
            ]
        );
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

    /** @param array<string,mixed> $input */
    public function createCoupon(array $input): int
    {
        $actor = $this->administrator();
        $repository = new FinanceRepository();

        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $type = strtolower(trim((string) ($input['discount_type'] ?? 'percent')));
        $value = $this->decimal($input['discount_value'] ?? '', 'Discount value');

        if (
            mb_strlen($code) < 2
            || mb_strlen($code) > 64
            || preg_match('/^[A-Z0-9][A-Z0-9_-]*$/', $code) !== 1
        ) {
            throw new InvalidArgumentException(
                'Coupon code must contain 2 to 64 uppercase letters, numbers, underscores, or hyphens.'
            );
        }

        if ($repository->couponCodeExists($code)) {
            throw new InvalidArgumentException('This coupon code is already in use.');
        }

        if (mb_strlen($name) < 2 || mb_strlen($name) > 160) {
            throw new InvalidArgumentException(
                'Coupon name must contain between 2 and 160 characters.'
            );
        }

        if (mb_strlen($description) > 5000) {
            throw new InvalidArgumentException(
                'Coupon description cannot exceed 5,000 characters.'
            );
        }

        if (!in_array($type, ['percent', 'fixed'], true)) {
            throw new InvalidArgumentException('Choose percent or fixed discount type.');
        }

        if ((float) $value <= 0) {
            throw new InvalidArgumentException('Discount value must be greater than zero.');
        }

        $currency = null;

        if ($type === 'percent') {
            if ((float) $value > 100) {
                throw new InvalidArgumentException(
                    'Percentage discounts cannot exceed 100 percent.'
                );
            }
        } else {
            $currency = strtoupper(trim((string) ($input['currency'] ?? '')));

            if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                throw new InvalidArgumentException(
                    'Fixed discounts require a three-letter currency code.'
                );
            }
        }

        $startsAt = $this->dateTime($input['starts_at'] ?? null, 'Start date');
        $endsAt = $this->dateTime($input['ends_at'] ?? null, 'End date');

        if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
            throw new InvalidArgumentException('Coupon end date must be after its start date.');
        }

        $id = $repository->createCoupon([
            'code' => $code,
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'discount_type' => $type,
            'discount_value' => $value,
            'currency' => $currency,
            'max_redemptions' => $this->nullablePositiveLimit(
                $input['max_redemptions'] ?? null,
                'Maximum redemptions'
            ),
            'per_company_limit' => $this->nullablePositiveLimit(
                $input['per_company_limit'] ?? null,
                'Per-company redemption limit'
            ),
            'starts_at' => $startsAt?->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt?->format('Y-m-d H:i:s'),
            'actor_id' => (int) $actor['id'],
        ]);

        (new SecurityAuditService())->record(
            'admin.billing_coupon_created',
            (int) $actor['id'],
            null,
            [
                'coupon_id' => $id,
                'code' => $code,
                'discount_type' => $type,
                'discount_value' => $value,
                'currency' => $currency,
            ]
        );

        return $id;
    }

    public function setCouponStatus(int $id, string $status): void
    {
        $actor = $this->administrator();
        $repository = new FinanceRepository();
        $coupon = $repository->coupon($id);

        if ($coupon === null) {
            throw new InvalidArgumentException('Coupon was not found.');
        }

        $current = (string) $coupon['status'];
        $status = strtolower(trim($status));

        $transitions = [
            'inactive' => ['active', 'archived'],
            'active' => ['inactive'],
            'archived' => [],
        ];

        if (!in_array($status, $transitions[$current] ?? [], true)) {
            throw new DomainException('This coupon status transition is not allowed.');
        }

        $repository->setCouponStatus($id, $status, (int) $actor['id']);

        (new SecurityAuditService())->record(
            'admin.billing_coupon_status_changed',
            (int) $actor['id'],
            null,
            [
                'coupon_id' => $id,
                'code' => (string) $coupon['code'],
                'from_status' => $current,
                'to_status' => $status,
            ]
        );
    }

    /** @param array<string,mixed> $input */
    public function createTaxRule(array $input): int
    {
        $actor = $this->administrator();
        $repository = new FinanceRepository();

        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $rate = $this->percentageRate($input['rate_percent'] ?? '', 'Tax rate');
        $country = strtoupper(trim((string) ($input['country_code'] ?? '')));
        $region = trim((string) ($input['region_code'] ?? ''));

        if (
            mb_strlen($code) < 2
            || mb_strlen($code) > 64
            || preg_match('/^[A-Z0-9][A-Z0-9_-]*$/', $code) !== 1
        ) {
            throw new InvalidArgumentException(
                'Tax rule code must contain 2 to 64 uppercase letters, numbers, underscores, or hyphens.'
            );
        }

        if ($repository->taxRuleCodeExists($code)) {
            throw new InvalidArgumentException('This tax rule code is already in use.');
        }

        if (mb_strlen($name) < 2 || mb_strlen($name) > 160) {
            throw new InvalidArgumentException(
                'Tax rule name must contain between 2 and 160 characters.'
            );
        }

        if (mb_strlen($description) > 5000) {
            throw new InvalidArgumentException(
                'Tax rule description cannot exceed 5,000 characters.'
            );
        }

        if ($country !== '' && preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            throw new InvalidArgumentException(
                'Country code must use two uppercase ISO letters.'
            );
        }

        if (mb_strlen($region) > 64) {
            throw new InvalidArgumentException('Region code cannot exceed 64 characters.');
        }

        $startsAt = $this->dateTime($input['starts_at'] ?? null, 'Start date');
        $endsAt = $this->dateTime($input['ends_at'] ?? null, 'End date');

        if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
            throw new InvalidArgumentException('Tax rule end date must be after its start date.');
        }

        $id = $repository->createTaxRule([
            'code' => $code,
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'rate_percent' => $rate,
            'country_code' => $country !== '' ? $country : null,
            'region_code' => $region !== '' ? $region : null,
            'starts_at' => $startsAt?->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt?->format('Y-m-d H:i:s'),
            'actor_id' => (int) $actor['id'],
        ]);

        (new SecurityAuditService())->record(
            'admin.billing_tax_rule_created',
            (int) $actor['id'],
            null,
            [
                'tax_rule_id' => $id,
                'code' => $code,
                'rate_percent' => $rate,
                'country_code' => $country !== '' ? $country : null,
                'region_code' => $region !== '' ? $region : null,
            ]
        );

        return $id;
    }

    public function setTaxRuleStatus(int $id, string $status): void
    {
        $actor = $this->administrator();
        $repository = new FinanceRepository();
        $rule = $repository->taxRule($id);

        if ($rule === null) {
            throw new InvalidArgumentException('Tax rule was not found.');
        }

        $current = (string) $rule['status'];
        $status = strtolower(trim($status));

        $transitions = [
            'inactive' => ['active', 'archived'],
            'active' => ['inactive'],
            'archived' => [],
        ];

        if (!in_array($status, $transitions[$current] ?? [], true)) {
            throw new DomainException('This tax rule status transition is not allowed.');
        }

        $repository->setTaxRuleStatus($id, $status, (int) $actor['id']);

        (new SecurityAuditService())->record(
            'admin.billing_tax_rule_status_changed',
            (int) $actor['id'],
            null,
            [
                'tax_rule_id' => $id,
                'code' => (string) $rule['code'],
                'from_status' => $current,
                'to_status' => $status,
            ]
        );
    }

    /** @param array<string,mixed> $input */
    public function createPaymentProvider(array $input): int
    {
        $actor = $this->administrator();
        $repository = new FinanceRepository();

        $code = strtolower(trim((string) ($input['code'] ?? '')));
        $name = trim((string) ($input['name'] ?? ''));
        $environment = strtolower(trim((string) ($input['environment'] ?? 'sandbox')));
        $token = trim((string) ($input['webhook_token'] ?? ''));

        if (
            mb_strlen($code) < 2
            || mb_strlen($code) > 24
            || preg_match('/^[a-z0-9][a-z0-9_-]*$/', $code) !== 1
        ) {
            throw new InvalidArgumentException(
                'Provider code must contain 2 to 24 lowercase letters, numbers, underscores, or hyphens.'
            );
        }

        if ($repository->paymentProviderCodeExists($code)) {
            throw new InvalidArgumentException('This payment provider code is already in use.');
        }

        if (mb_strlen($name) < 2 || mb_strlen($name) > 160) {
            throw new InvalidArgumentException(
                'Payment provider name must contain between 2 and 160 characters.'
            );
        }

        if (!in_array($environment, ['sandbox', 'live'], true)) {
            throw new InvalidArgumentException('Choose sandbox or live provider environment.');
        }

        if (strlen($token) < 32 || strlen($token) > 191) {
            throw new InvalidArgumentException(
                'Webhook token must contain between 32 and 191 characters.'
            );
        }

        $tokenHash = password_hash($token, PASSWORD_DEFAULT);

        if (!is_string($tokenHash) || $tokenHash === '') {
            throw new DomainException('Webhook token hashing failed.');
        }

        $id = $repository->createPaymentProvider([
            'code' => $code,
            'name' => $name,
            'adapter_key' => 'generic_token',
            'environment' => $environment,
            'webhook_token_hash' => $tokenHash,
            'actor_id' => (int) $actor['id'],
        ]);

        (new SecurityAuditService())->record(
            'admin.billing_payment_provider_created',
            (int) $actor['id'],
            null,
            [
                'payment_provider_id' => $id,
                'code' => $code,
                'environment' => $environment,
                'adapter_key' => 'generic_token',
            ]
        );

        return $id;
    }

    public function setPaymentProviderStatus(int $id, string $status): void
    {
        $actor = $this->administrator();
        $repository = new FinanceRepository();
        $provider = $repository->paymentProvider($id);

        if ($provider === null) {
            throw new InvalidArgumentException('Payment provider was not found.');
        }

        $current = (string) $provider['status'];
        $status = strtolower(trim($status));

        $transitions = [
            'inactive' => ['active', 'archived'],
            'active' => ['inactive'],
            'archived' => [],
        ];

        if (!in_array($status, $transitions[$current] ?? [], true)) {
            throw new DomainException('This payment provider status transition is not allowed.');
        }

        $repository->setPaymentProviderStatus($id, $status, (int) $actor['id']);

        (new SecurityAuditService())->record(
            'admin.billing_payment_provider_status_changed',
            (int) $actor['id'],
            null,
            [
                'payment_provider_id' => $id,
                'code' => (string) $provider['code'],
                'from_status' => $current,
                'to_status' => $status,
            ]
        );
    }

    /** @param array<string,mixed> $input */
    public function createInvoice(array $input): int
    {
        $actor = $this->administrator();
        $repository = new FinanceRepository();

        $companyId = $this->positiveId($input['company_id'] ?? null, 'Company');

        if (!$repository->companyExists($companyId)) {
            throw new InvalidArgumentException('Company is unavailable for invoicing.');
        }

        $subscriptionId = null;
        if (trim((string) ($input['subscription_id'] ?? '')) !== '') {
            $subscriptionId = $this->positiveId(
                $input['subscription_id'],
                'Subscription'
            );

            if (!$repository->subscriptionBelongsToCompany($subscriptionId, $companyId)) {
                throw new InvalidArgumentException(
                    'The selected subscription does not belong to this company.'
                );
            }
        }

        $currency = strtoupper(trim((string) ($input['currency'] ?? 'USD')));

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('Currency must use a three-letter ISO code.');
        }

        $description = trim((string) ($input['description'] ?? ''));
        if ($description === '' || mb_strlen($description) > 255) {
            throw new InvalidArgumentException(
                'Invoice line description is required and cannot exceed 255 characters.'
            );
        }

        $quantity = $this->quantity($input['quantity'] ?? '1');
        $unitAmount = $this->decimal($input['unit_amount'] ?? '0', 'Unit amount');
        $lineTotal = number_format(
            round(((float) $quantity) * ((float) $unitAmount), 2),
            2,
            '.',
            ''
        );

        $couponCode = strtoupper(trim((string) ($input['coupon_code'] ?? '')));
        $taxRuleCode = strtoupper(trim((string) ($input['tax_rule_code'] ?? '')));
        $dueAt = $this->dateTime($input['due_at'] ?? null, 'Due date');
        $notes = trim((string) ($input['notes'] ?? ''));

        if (mb_strlen($couponCode) > 64) {
            throw new InvalidArgumentException('Coupon code cannot exceed 64 characters.');
        }

        if (mb_strlen($taxRuleCode) > 64) {
            throw new InvalidArgumentException('Tax rule code cannot exceed 64 characters.');
        }

        if (mb_strlen($notes) > 5000) {
            throw new InvalidArgumentException('Invoice notes cannot exceed 5,000 characters.');
        }

        $pdo = Database::connection();
        $coupon = null;
        $taxRule = null;
        $discountTotal = '0.00';
        $taxableTotal = $lineTotal;
        $taxRate = '0.0000';
        $taxTotal = '0.00';
        $invoiceTotal = $lineTotal;

        try {
            $pdo->beginTransaction();

            if ($couponCode !== '') {
                $coupon = $repository->lockCouponByCode($couponCode);

                if ($coupon === null || (string) $coupon['status'] !== 'active') {
                    throw new InvalidArgumentException('Coupon is unavailable or inactive.');
                }

                $now = new DateTimeImmutable();
                $startsAt = !empty($coupon['starts_at'])
                    ? new DateTimeImmutable((string) $coupon['starts_at'])
                    : null;
                $endsAt = !empty($coupon['ends_at'])
                    ? new DateTimeImmutable((string) $coupon['ends_at'])
                    : null;

                if ($startsAt !== null && $now < $startsAt) {
                    throw new DomainException('Coupon is not active yet.');
                }

                if ($endsAt !== null && $now >= $endsAt) {
                    throw new DomainException('Coupon has expired.');
                }

                $maxRedemptions = $coupon['max_redemptions'] !== null
                    ? (int) $coupon['max_redemptions']
                    : null;
                $perCompanyLimit = $coupon['per_company_limit'] !== null
                    ? (int) $coupon['per_company_limit']
                    : null;

                if (
                    $maxRedemptions !== null
                    && $repository->effectiveCouponRedemptions((int) $coupon['id'])
                        >= $maxRedemptions
                ) {
                    throw new DomainException('Coupon redemption limit has been reached.');
                }

                if (
                    $perCompanyLimit !== null
                    && $repository->effectiveCouponRedemptions(
                        (int) $coupon['id'],
                        $companyId
                    ) >= $perCompanyLimit
                ) {
                    throw new DomainException(
                        'This company has reached the coupon redemption limit.'
                    );
                }

                if ((string) $coupon['discount_type'] === 'fixed') {
                    if (strtoupper((string) $coupon['currency']) !== $currency) {
                        throw new DomainException(
                            'Fixed coupon currency must match the invoice currency.'
                        );
                    }

                    $discount = (float) $coupon['discount_value'];
                } else {
                    $discount = ((float) $lineTotal)
                        * ((float) $coupon['discount_value'] / 100);
                }

                $discount = min((float) $lineTotal, round($discount, 2));
                $discountTotal = number_format($discount, 2, '.', '');
            }

            $taxable = max(0, (float) $lineTotal - (float) $discountTotal);
            $taxableTotal = number_format($taxable, 2, '.', '');

            if ($taxRuleCode !== '') {
                $taxRule = $repository->lockTaxRuleByCode($taxRuleCode);

                if ($taxRule === null || (string) $taxRule['status'] !== 'active') {
                    throw new InvalidArgumentException('Tax rule is unavailable or inactive.');
                }

                $now = new DateTimeImmutable();
                $startsAt = !empty($taxRule['starts_at'])
                    ? new DateTimeImmutable((string) $taxRule['starts_at'])
                    : null;
                $endsAt = !empty($taxRule['ends_at'])
                    ? new DateTimeImmutable((string) $taxRule['ends_at'])
                    : null;

                if ($startsAt !== null && $now < $startsAt) {
                    throw new DomainException('Tax rule is not active yet.');
                }

                if ($endsAt !== null && $now >= $endsAt) {
                    throw new DomainException('Tax rule has expired.');
                }

                $taxRate = number_format((float) $taxRule['rate_percent'], 4, '.', '');
                $tax = round($taxable * ((float) $taxRate / 100), 2);
                $taxTotal = number_format($tax, 2, '.', '');
            }

            $invoiceTotal = number_format(
                $taxable + (float) $taxTotal,
                2,
                '.',
                ''
            );

            $invoiceId = $repository->createInvoice([
                'company_id' => $companyId,
                'subscription_id' => $subscriptionId,
                'coupon_id' => $coupon !== null ? (int) $coupon['id'] : null,
                'tax_rule_id' => $taxRule !== null ? (int) $taxRule['id'] : null,
                'currency' => $currency,
                'subtotal' => $lineTotal,
                'discount_total' => $discountTotal,
                'taxable_total' => $taxableTotal,
                'tax_rate' => $taxRate,
                'tax_total' => $taxTotal,
                'total' => $invoiceTotal,
                'due_at' => $dueAt?->format('Y-m-d H:i:s'),
                'notes' => $notes !== '' ? $notes : null,
                'actor_id' => (int) $actor['id'],
            ]);

            $repository->createInvoiceItem($invoiceId, [
                'description' => $description,
                'quantity' => $quantity,
                'unit_amount' => $unitAmount,
                'line_total' => $lineTotal,
            ]);

            if ($coupon !== null) {
                $repository->createCouponRedemption(
                    (int) $coupon['id'],
                    $invoiceId,
                    $companyId,
                    $discountTotal,
                    $currency,
                    (int) $actor['id']
                );
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        (new SecurityAuditService())->record(
            'admin.billing_invoice_created',
            (int) $actor['id'],
            null,
            [
                'invoice_id' => $invoiceId,
                'company_id' => $companyId,
                'subscription_id' => $subscriptionId,
                'coupon_id' => $coupon !== null ? (int) $coupon['id'] : null,
                'coupon_code' => $coupon !== null ? (string) $coupon['code'] : null,
                'tax_rule_id' => $taxRule !== null ? (int) $taxRule['id'] : null,
                'tax_rule_code' => $taxRule !== null ? (string) $taxRule['code'] : null,
                'subtotal' => $lineTotal,
                'discount_total' => $discountTotal,
                'taxable_total' => $taxableTotal,
                'tax_rate' => $taxRate,
                'tax_total' => $taxTotal,
                'total' => $invoiceTotal,
                'currency' => $currency,
            ]
        );

        return $invoiceId;
    }

    public function setInvoiceStatus(int $id, string $status): void
    {
        $actor = $this->administrator();
        $repository = new FinanceRepository();
        $invoice = $repository->invoice($id);

        if ($invoice === null) {
            throw new InvalidArgumentException('Invoice was not found.');
        }

        $current = (string) $invoice['status'];
        $status = strtolower(trim($status));

        $transitions = [
            'draft' => ['open', 'void'],
            'open' => ['paid', 'void'],
            'paid' => [],
            'void' => [],
        ];

        if (!in_array($status, $transitions[$current] ?? [], true)) {
            throw new DomainException('This invoice status transition is not allowed.');
        }

        $settled = $repository->invoiceSettledAmount($id);

        if ($status === 'paid' && $settled + 0.00001 < (float) $invoice['total']) {
            throw new DomainException(
                'Record sufficient successful payment or credit transactions before marking this invoice paid.'
            );
        }

        if ($status === 'void' && $settled > 0.00001) {
            throw new DomainException(
                'An invoice with recorded settlement cannot be voided. Record any required refund first.'
            );
        }

        $repository->setInvoiceStatus($id, $status, (int) $actor['id']);

        (new SecurityAuditService())->record(
            'admin.billing_invoice_status_changed',
            (int) $actor['id'],
            null,
            [
                'invoice_id' => $id,
                'from_status' => $current,
                'to_status' => $status,
                'settled_amount' => number_format($settled, 2, '.', ''),
            ]
        );
    }

    /** @param array<string,mixed> $input */
    public function createTransaction(array $input): int
    {
        $actor = $this->administrator();
        $repository = new FinanceRepository();

        $companyId = $this->positiveId($input['company_id'] ?? null, 'Company');

        if (!$repository->companyExists($companyId)) {
            throw new InvalidArgumentException('Company is unavailable for transaction recording.');
        }

        $subscriptionId = null;
        if (trim((string) ($input['subscription_id'] ?? '')) !== '') {
            $subscriptionId = $this->positiveId(
                $input['subscription_id'],
                'Subscription'
            );

            if (!$repository->subscriptionBelongsToCompany($subscriptionId, $companyId)) {
                throw new InvalidArgumentException(
                    'The selected subscription does not belong to this company.'
                );
            }
        }

        $invoiceId = null;
        $invoice = null;
        if (trim((string) ($input['invoice_id'] ?? '')) !== '') {
            $invoiceId = $this->positiveId($input['invoice_id'], 'Invoice');
            $invoice = $repository->invoice($invoiceId);

            if ($invoice === null || (int) $invoice['company_id'] !== $companyId) {
                throw new InvalidArgumentException(
                    'The selected invoice does not belong to this company.'
                );
            }
        }

        $type = strtolower(trim((string) ($input['transaction_type'] ?? 'payment')));
        $status = strtolower(trim((string) ($input['status'] ?? 'succeeded')));

        if (!in_array($type, ['payment', 'refund', 'credit'], true)) {
            throw new InvalidArgumentException('Choose a supported transaction type.');
        }

        if (!in_array($status, ['pending', 'succeeded', 'failed'], true)) {
            throw new InvalidArgumentException('Choose a supported transaction status.');
        }

        if ($type === 'refund' && $invoice === null) {
            throw new InvalidArgumentException(
                'Refund transactions must be linked to an invoice.'
            );
        }

        $amount = $this->decimal($input['amount'] ?? '', 'Transaction amount');

        if ((float) $amount <= 0) {
            throw new InvalidArgumentException('Transaction amount must be greater than zero.');
        }

        $currency = strtoupper(trim((string) ($input['currency'] ?? 'USD')));

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException('Currency must use a three-letter ISO code.');
        }

        if ($invoice !== null && strtoupper((string) $invoice['currency']) !== $currency) {
            throw new InvalidArgumentException(
                'Transaction currency must match the linked invoice currency.'
            );
        }

        if (
            $type === 'refund'
            && $status === 'succeeded'
            && $invoiceId !== null
            && $repository->invoiceSettledAmount($invoiceId) + 0.00001 < (float) $amount
        ) {
            throw new DomainException(
                'Successful refunds cannot exceed the currently settled amount of the linked invoice.'
            );
        }

        $reference = trim((string) ($input['reference'] ?? ''));
        $notes = trim((string) ($input['notes'] ?? ''));
        $processedAt = $this->dateTime(
            $input['processed_at'] ?? null,
            'Processed date'
        ) ?? new DateTimeImmutable();

        if (mb_strlen($reference) > 191) {
            throw new InvalidArgumentException('Transaction reference cannot exceed 191 characters.');
        }

        if (mb_strlen($notes) > 5000) {
            throw new InvalidArgumentException('Transaction notes cannot exceed 5,000 characters.');
        }

        $id = $repository->createTransaction([
            'company_id' => $companyId,
            'subscription_id' => $subscriptionId,
            'invoice_id' => $invoiceId,
            'transaction_type' => $type,
            'status' => $status,
            'reference' => $reference !== '' ? $reference : null,
            'amount' => $amount,
            'currency' => $currency,
            'processed_at' => $processedAt->format('Y-m-d H:i:s'),
            'notes' => $notes !== '' ? $notes : null,
            'actor_id' => (int) $actor['id'],
        ]);

        (new SecurityAuditService())->record(
            'admin.billing_transaction_recorded',
            (int) $actor['id'],
            null,
            [
                'transaction_id' => $id,
                'company_id' => $companyId,
                'invoice_id' => $invoiceId,
                'transaction_type' => $type,
                'status' => $status,
                'amount' => $amount,
                'currency' => $currency,
            ]
        );

        return $id;
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

    private function nullablePositiveLimit(mixed $value, string $label): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $this->integer($value, $label, 1, 1000000);
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

    private function quantity(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '' || !preg_match('/^\\d{1,8}(?:\\.\\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException(
                'Invoice quantity must be a positive number with up to two decimal places.'
            );
        }

        if ((float) $value <= 0) {
            throw new InvalidArgumentException('Invoice quantity must be greater than zero.');
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function percentageRate(mixed $value, string $label): string
    {
        $value = trim((string) $value);

        if ($value === '' || !preg_match('/^\\d{1,3}(?:\\.\\d{1,4})?$/', $value)) {
            throw new InvalidArgumentException(
                $label . ' must be a valid percentage with up to four decimal places.'
            );
        }

        if ((float) $value <= 0 || (float) $value > 100) {
            throw new InvalidArgumentException(
                $label . ' must be greater than zero and no more than 100.'
            );
        }

        return number_format((float) $value, 4, '.', '');
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
