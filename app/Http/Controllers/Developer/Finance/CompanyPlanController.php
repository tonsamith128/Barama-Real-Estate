<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer\Finance;

use App\Models\CommercialPlan;
use App\Models\Company;
use App\Models\CompanyPlanInquiry;
use App\Services\Developer\CompanyTeamManager;
use App\Services\Billing\BillingCheckout;
use App\Services\Billing\SubscriptionEntitlements;
use App\Models\BillingInvoice;
use App\Models\BillingOrder;
use App\Models\CompanySubscription;
use App\Models\CompanyBillingProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class CompanyPlanController
{
    private function authorizeOwner(Request $request, Company $company, CompanyTeamManager $teams): void
    {
        $teams->assertOwner($request->user(), $company);
        abort_unless(Schema::hasTable('commercial_plans')
            && Schema::hasTable('commercial_plan_inquiries'), 503, 'Run pending database migrations.');
    }

    public function index(Request $request, Company $company, CompanyTeamManager $teams): View
    {
        $this->authorizeOwner($request, $company, $teams);

        return view('developer.finance.plans.index', [
            'company' => $company,
            'plans' => CommercialPlan::query()->published()
                ->orderBy('sort_order')->orderBy('name')->get(),
            'billingEnabled' => (bool) config('billing.enabled'),
            'subscription' => Schema::hasTable('company_subscriptions')
                ? CompanySubscription::query()->where('company_id', $company->getKey())->first()
                : null,
            'billingProfile' => Schema::hasTable('company_billing_profiles')
                ? CompanyBillingProfile::query()->where('company_id', $company->getKey())->first()
                : null,
            'managementEnabled' => config('billing.enabled') && config('billing.recurring_test_enabled')
                && config('billing.subscription_management_test_enabled'),
            'portalEnabled' => config('billing.stripe.portal_test_enabled'),
            'orders' => Schema::hasTable('billing_orders')
                ? BillingOrder::query()->with('invoice')->where('company_id', $company->getKey())->latest()->limit(10)->get()
                : collect(),
            'inquiry' => CompanyPlanInquiry::query()->with('plan')
                ->where('company_id', $company->getKey())->first(),
        ]);
    }

    public function checkout(
        Request $request,
        Company $company,
        CommercialPlan $plan,
        CompanyTeamManager $teams,
        BillingCheckout $checkout,
    ): RedirectResponse {
        $this->authorizeOwner($request, $company, $teams);
        abort_unless(Schema::hasTable('billing_orders') && Schema::hasTable('billing_invoices')
            && Schema::hasTable('company_subscriptions'), 503, 'Run pending billing migrations.');
        $session = $checkout->start($request->user(), $company, $plan);
        return redirect()->away($session['url']);
    }

    public function inquire(
        Request $request,
        Company $company,
        CommercialPlan $plan,
        CompanyTeamManager $teams,
    ): RedirectResponse {
        $this->authorizeOwner($request, $company, $teams);
        abort_unless($plan->is_active, 404);

        DB::transaction(function () use ($request, $company, $plan): void {
            // A company has only one current inquiry; this is NOT a subscription.
            CompanyPlanInquiry::query()->updateOrCreate(
                ['company_id' => $company->getKey()],
                [
                    'commercial_plan_id' => $plan->getKey(),
                    'requested_by_user_id' => $request->user()->getKey(),
                    'status' => 'pending',
                ],
            );

            DB::table('audit_events')->insert([
                'actor_id' => $request->user()->getKey(),
                'action' => 'finance.plan.inquiry',
                'subject_type' => 'company',
                'subject_id' => $company->getKey(),
                'context' => json_encode(['plan_id' => $plan->getKey()], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return back()->with('notice', 'Plan inquiry recorded. No payment was taken and no paid features were activated.');
    }
}
