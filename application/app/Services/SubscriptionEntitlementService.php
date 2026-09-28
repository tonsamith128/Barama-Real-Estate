<?php

declare(strict_types=1);

namespace Barama\Services;

use Barama\Repositories\FinanceRepository;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class SubscriptionEntitlementService
{
    private const RESOURCES = [
        'project' => [
            'limit' => 'project_limit',
            'usage' => 'projects',
            'label' => 'project',
        ],
        'property' => [
            'limit' => 'property_limit',
            'usage' => 'properties',
            'label' => 'property',
        ],
        'listing' => [
            'limit' => 'listing_limit',
            'usage' => 'listings',
            'label' => 'listing',
        ],
        'team_seat' => [
            'limit' => 'team_member_limit',
            'usage' => 'team_seats',
            'label' => 'team seat',
        ],
    ];

    public function assertCanConsume(int $companyId, string $resource): void
    {
        if ($companyId < 1) {
            throw new InvalidArgumentException('A valid company ID is required.');
        }

        if (!isset(self::RESOURCES[$resource])) {
            throw new InvalidArgumentException('Unknown subscription entitlement.');
        }

        $finance = new FinanceRepository();
        $finance->lockCompany($companyId);

        $subscription = $finance->companyEntitlementSubscription(
            $companyId,
            true
        );

        if ($subscription === null) {
            // Legacy compatibility: companies never enrolled in billing remain
            // unmetered until an administrator assigns their first subscription.
            return;
        }

        $status = (string) $subscription['status'];

        if ($status !== 'active') {
            throw new DomainException(
                'This company subscription is ' . $status
                . '. Activate or replace the subscription before adding new '
                . self::RESOURCES[$resource]['label'] . ' capacity.'
            );
        }

        $now = new DateTimeImmutable();

        if (
            !empty($subscription['starts_at'])
            && new DateTimeImmutable((string) $subscription['starts_at']) > $now
        ) {
            throw new DomainException(
                'This company subscription has not started yet.'
            );
        }

        if (
            !empty($subscription['expires_at'])
            && new DateTimeImmutable((string) $subscription['expires_at']) <= $now
        ) {
            throw new DomainException(
                'This company subscription has expired. Assign or renew a plan '
                . 'before adding new capacity.'
            );
        }

        $definition = self::RESOURCES[$resource];
        $limit = $subscription[$definition['limit']];

        if ($limit === null) {
            return;
        }

        $usage = $finance->companyEntitlementUsage($companyId);
        $used = (int) ($usage[$definition['usage']] ?? 0);
        $limit = (int) $limit;

        if ($used >= $limit) {
            throw new DomainException(
                sprintf(
                    'The %s plan limit has been reached (%d of %d).',
                    $definition['label'],
                    $used,
                    $limit
                )
            );
        }
    }
}
