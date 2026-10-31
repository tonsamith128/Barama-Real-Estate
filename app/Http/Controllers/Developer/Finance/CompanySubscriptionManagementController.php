<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer\Finance;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Services\Billing\StripeTestSubscriptionManager;
use App\Services\Developer\CompanyTeamManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

final class CompanySubscriptionManagementController
{
    private function subscription(
        Request $request, Company $company, CompanyTeamManager $teams,
    ): CompanySubscription {
        $teams->assertOwner($request->user(), $company);
        abort_unless(Schema::hasTable('company_subscriptions'), 503, 'Run pending billing migrations.');

        return CompanySubscription::query()->where('company_id', $company->getKey())->firstOrFail();
    }

    public function cancel(
        Request $request, Company $company, CompanyTeamManager $teams,
        StripeTestSubscriptionManager $manager,
    ): RedirectResponse {
        $subscription = $this->subscription($request, $company, $teams);
        $manager->cancelAtPeriodEnd($subscription, $request->user());
        return redirect()->route('developer.finance.plans.index', $company)
            ->with('notice', 'Stripe test subscription cancellation verified. Existing paid access is retained until its confirmed period ends.');
    }

    public function portal(
        Request $request, Company $company, CompanyTeamManager $teams,
        StripeTestSubscriptionManager $manager,
    ): RedirectResponse {
        $subscription = $this->subscription($request, $company, $teams);
        $url = $manager->portalUrl(
            $subscription, route('developer.finance.plans.index', $company),
        );
        return redirect()->away($url);
    }
}
