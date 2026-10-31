<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Property;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class SubscriptionEntitlements
{
    public function current(Company $company): ?CompanySubscription
    {
        if (!Schema::hasTable('company_subscriptions')) {
            return null;
        }
        $subscription = CompanySubscription::query()->where('company_id', $company->getKey())->first();

        return $subscription?->isCurrent() ? $subscription : null;
    }

    /**
     * Existing legacy companies retain their pre-billing behavior until an
     * explicit migration policy is approved. Only paid plans impose limits.
     */
    public function assertCanCreateListing(Company $company): void
    {
        if (!Schema::hasTable('company_subscriptions')) {
            return;
        }
        $subscription = CompanySubscription::query()->where('company_id', $company->getKey())->first();
        if ($subscription === null) {
            // Existing companies without a paid plan keep their legacy access.
            return;
        }
        if (!$subscription->isCurrent()) {
            throw ValidationException::withMessages([
                'subscription' => 'Your paid subscription is expired or revoked. Renew before creating listings.',
            ]);
        }

        // Count only non-deleted records; soft-deleted properties do not consume the quota.
        $used = Property::query()->where('company_id', $company->getKey())->count();
        if ($used >= $subscription->listing_limit) {
            throw ValidationException::withMessages([
                'listing_limit' => 'Your company has reached its current plan listing allowance.',
            ]);
        }
    }
}
