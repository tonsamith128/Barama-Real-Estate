<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\BillingOrder;
use App\Models\BillingInvoice;
use App\Models\CommercialPlan;
use App\Models\Company;
use App\Models\CompanyBillingProfile;
use App\Models\CompanyMembership;
use App\Models\CompanySubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class GlobalBillingCustomerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(ThrottleRequests::class);
        config([
            'billing.enabled' => true,
            'billing.recurring_test_enabled' => true,
            'billing.subscription_management_test_enabled' => true,
            'billing.stripe.secret_key' => 'sk_test_OnlyForTests',
            'billing.stripe.webhook_secret' => 'whsec_OnlyForTests',
            'billing.stripe.portal_test_enabled' => true,
            'billing.stripe.portal_configuration' => 'bpc_test_123',
        ]);
    }

    private function company(string $slug): Company
    {
        return Company::query()->create([
            'name' => 'Business '.$slug, 'slug' => $slug,
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
    }

    private function member(Company $company, string $role = 'owner'): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        CompanyMembership::query()->create([
            'company_id' => $company->id, 'user_id' => $user->id,
            'role' => $role, 'status' => 'active',
        ]);
        return $user;
    }

    private function profile(array $override = []): array
    {
        return array_replace([
            'billing_name' => 'International Property Company',
            'billing_email' => 'accounts@example.test',
            'country_code' => 'KH',
            'address_line' => 'Main Street 9', 'city' => 'Battambang',
            'region' => 'Battambang', 'postal_code' => '02000',
            'preferred_locale' => 'km',
            'preferred_currency' => 'KHR',
            'timezone' => 'Asia/Phnom_Penh',
        ], $override);
    }

    private function paidSubscription(Company $company, User $owner): CompanySubscription
    {
        $plan = CommercialPlan::query()->create([
            'slug' => 'international', 'name' => 'International Plan',
            'price_cents' => 2499, 'currency' => 'USD', 'billing_period' => 'month',
            'listing_limit' => 25, 'is_active' => true,
        ]);
        $order = BillingOrder::query()->create([
            'company_id' => $company->id, 'commercial_plan_id' => $plan->id,
            'requested_by_user_id' => $owner->id,
            'reference' => '00000000-0000-4000-8000-000000000001',
            'gateway' => 'stripe_test', 'checkout_mode' => 'subscription',
            'status' => 'paid', 'plan_name' => 'International Plan',
            'amount_cents' => 2499, 'currency' => 'USD',
            'billing_period' => 'month', 'listing_limit' => 25,
        ]);
        return CompanySubscription::query()->create([
            'company_id' => $company->id, 'commercial_plan_id' => $plan->id,
            'billing_order_id' => $order->id,
            'status' => 'active', 'billing_period' => 'month',
            'listing_limit' => 25, 'starts_at' => now()->subDays(3),
            'ends_at' => now()->addDays(28),
            'gateway_subscription_id' => 'sub_test_global001',
            'gateway_customer_id' => 'cus_test_global001',
        ]);
    }

    public function test_company_owner_can_save_global_preferences_without_changing_charge_currency(): void
    {
        $company = $this->company('alpha');
        $owner = $this->member($company);
        $this->actingAs($owner)->put(route('developer.finance.profile.update', $company), $this->profile())
            ->assertRedirect(route('developer.finance.profile.edit', $company));
        $saved = CompanyBillingProfile::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertSame('KH', $saved->country_code);
        $this->assertSame('km', $saved->preferred_locale);
        $this->assertSame('KHR', $saved->preferred_currency);
        $this->get(route('developer.finance.profile.edit', $company))->assertOk()
            ->assertSee('International Property Company')
            ->assertSee('ព័ត៌មានទូទាត់')
            ->assertSee('Payment currency remains USD', false);
        $this->get(route('developer.finance.plans.index', $company))->assertOk()
            ->assertSee('Currency conversion and tax calculation are not provided.');
        $audit = DB::table('audit_events')->where('action', 'billing.profile.updated')->firstOrFail();
        $this->assertStringNotContainsString('accounts@example.test', (string) $audit->context);
        $this->assertDatabaseCount('company_billing_profiles', 1);
    }

    public function test_profile_is_owner_only_and_isolated_per_company(): void
    {
        $company = $this->company('private');
        $outsider = $this->member($this->company('outside'));
        $this->actingAs($outsider)->get(route('developer.finance.profile.edit', $company))
            ->assertForbidden();
        $this->put(route('developer.finance.profile.update', $company), $this->profile())
            ->assertForbidden();
        $manager = $this->member($company, 'manager');
        $this->actingAs($manager)->put(route('developer.finance.profile.update', $company), $this->profile())
            ->assertForbidden();
        $this->assertDatabaseCount('company_billing_profiles', 0);
    }

    public function test_invalid_country_locale_currency_and_timezone_are_rejected(): void
    {
        $company = $this->company('validate');
        $this->actingAs($this->member($company))
            ->put(route('developer.finance.profile.update', $company), $this->profile([
                'country_code' => 'ZZ', 'preferred_locale' => 'xx',
                'preferred_currency' => 'BTC', 'timezone' => 'Unknown/Zone',
            ]))->assertSessionHasErrors([
                'country_code', 'preferred_locale', 'preferred_currency', 'timezone',
            ]);
        $this->assertDatabaseCount('company_billing_profiles', 0);
    }

    public function test_checkout_preserves_billing_identity_snapshot_after_profile_change(): void
    {
        $company = $this->company('snapshot');
        $owner = $this->member($company);
        $this->actingAs($owner)->put(route('developer.finance.profile.update', $company),
            $this->profile())->assertRedirect();
        $plan = CommercialPlan::query()->create([
            'slug' => 'one-time', 'name' => 'Global One Time',
            'price_cents' => 1999, 'currency' => 'USD',
            'billing_period' => 'once', 'listing_limit' => 10, 'is_active' => true,
        ]);
        Http::fake(['api.stripe.com/*' => Http::response([
            'id' => 'cs_test_global_001',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_global_001',
            'amount_total' => 1999, 'currency' => 'usd',
            'mode' => 'payment', 'livemode' => false,
        ])]);
        $this->post(route('developer.finance.plans.checkout', [$company, $plan]))->assertRedirect();
        $order = BillingOrder::query()->firstOrFail();
        $this->assertSame('USD', $order->currency);
        $this->assertSame(1999, $order->amount_cents);
        $this->assertSame('KH', $order->invoice->customer_country_code);
        $this->assertSame('International Property Company', $order->invoice->customer_billing_name);
        Http::assertSent(function ($request): bool {
            return ($request['billing_address_collection'] ?? null) === 'required'
                && ($request['line_items'][0]['price_data']['currency'] ?? null) === 'usd'
                && ($request['line_items'][0]['price_data']['unit_amount'] ?? null) === 1999;
        });
        $this->put(route('developer.finance.profile.update', $company),
            $this->profile(['country_code' => 'SG', 'billing_name' => 'New Name']))
            ->assertRedirect();
        $this->assertSame('KH', $order->fresh()->invoice->customer_country_code);
        $this->assertSame('International Property Company', $order->fresh()->invoice->customer_billing_name);
    }

    public function test_verified_test_cancellation_is_idempotent_and_keeps_paid_period(): void
    {
        $company = $this->company('cancel');
        $owner = $this->member($company);
        $subscription = $this->paidSubscription($company, $owner);
        Http::fake(['api.stripe.com/*' => Http::response([
            'object' => 'subscription',
            'id' => 'sub_test_global001', 'customer' => 'cus_test_global001',
            'livemode' => false, 'cancel_at_period_end' => true,
            'status' => 'active',
        ])]);
        $this->actingAs($owner)->post(route('developer.finance.subscription.cancel', $company))
            ->assertRedirect(route('developer.finance.plans.index', $company));
        $this->assertTrue($subscription->fresh()->cancel_at_period_end);
        $this->assertTrue($subscription->fresh()->isCurrent());
        $this->post(route('developer.finance.subscription.cancel', $company))->assertRedirect();
        Http::assertSentCount(1);
        $this->assertSame(1, DB::table('audit_events')
            ->where('action', 'billing.subscription.cancel_scheduled')->count());
    }

    public function test_unauthorized_or_disabled_management_never_calls_gateway(): void
    {
        $company = $this->company('protected');
        $owner = $this->member($company);
        $this->paidSubscription($company, $owner);
        $outsider = $this->member($this->company('unrelated'));
        Http::fake();
        $this->actingAs($outsider)->post(route('developer.finance.subscription.cancel', $company))
            ->assertForbidden();
        $this->post(route('developer.finance.subscription.portal', $company))->assertForbidden();
        $this->actingAs($owner);
        config(['billing.subscription_management_test_enabled' => false]);
        $this->post(route('developer.finance.subscription.cancel', $company))->assertStatus(503);
        $this->post(route('developer.finance.subscription.portal', $company))->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_invalid_provider_cancellation_does_not_change_entitlement(): void
    {
        $company = $this->company('mismatch');
        $owner = $this->member($company);
        $sub = $this->paidSubscription($company, $owner);
        Http::fake(['api.stripe.com/*' => Http::response([
            'object' => 'subscription', 'id' => 'sub_someone_else',
            'customer' => 'cus_test_global001', 'livemode' => false,
            'cancel_at_period_end' => true,
        ])]);
        $this->actingAs($owner)->post(route('developer.finance.subscription.cancel', $company))
            ->assertStatus(500);
        $this->assertFalse($sub->fresh()->cancel_at_period_end);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_test_portal_is_scoped_and_requires_valid_provider_host(): void
    {
        $company = $this->company('portal');
        $owner = $this->member($company);
        $this->paidSubscription($company, $owner);
        Http::fake(['api.stripe.com/*' => Http::response([
            'object' => 'billing_portal.session',
            'customer' => 'cus_test_global001', 'livemode' => false,
            'url' => 'https://billing.stripe.com/p/session/test_001',
        ])]);
        $this->actingAs($owner)->post(route('developer.finance.subscription.portal', $company))
            ->assertRedirect('https://billing.stripe.com/p/session/test_001');
        Http::assertSent(function ($request) use ($company): bool {
            return ($request['customer'] ?? '') === 'cus_test_global001'
                && ($request['configuration'] ?? '') === 'bpc_test_123'
                && ($request['return_url'] ?? '') === route('developer.finance.plans.index', $company);
        });
        config(['billing.stripe.portal_test_enabled' => false]);
        $this->post(route('developer.finance.subscription.portal', $company))->assertStatus(503);
    }

    public function test_paid_receipt_uses_saved_khmer_locale_without_issuing_tax_invoice(): void
    {
        $company = $this->company('translated');
        $owner = $this->member($company);
        $this->actingAs($owner)->put(route('developer.finance.profile.update', $company),
            $this->profile())->assertRedirect();
        $subscription = $this->paidSubscription($company, $owner);
        $invoice = BillingInvoice::query()->create([
            'billing_order_id' => $subscription->billing_order_id,
            'invoice_number' => 'BRM-LOCALE-TEST',
            'status' => 'paid', 'currency' => 'USD',
            'total_cents' => 2499,
            'customer_billing_name' => 'International Property Company',
            'customer_country_code' => 'KH',
            'paid_at' => now(),
        ]);
        $this->get(route('developer.finance.receipts.show', [$company, $invoice]))
            ->assertOk()->assertSee('ការបញ្ជាក់ការទូទាត់')
            ->assertSee('NOT a tax invoice')
            ->assertSee('USD 24.99');
        $this->assertSame('KH', $invoice->fresh()->customer_country_code);
    }

    public function test_existing_paid_subscription_blocks_duplicate_recurring_checkout(): void
    {
        $company = $this->company('duplicate');
        $owner = $this->member($company);
        $sub = $this->paidSubscription($company, $owner);
        $this->actingAs($owner)
            ->post(route('developer.finance.plans.checkout', [$company, $sub->plan]))
            ->assertSessionHasErrors('subscription');
        $this->assertDatabaseCount('billing_orders', 1);
    }
}
