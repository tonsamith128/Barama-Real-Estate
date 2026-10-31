<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\BillingOrder;
use App\Models\BillingPayment;
use App\Models\BillingRefund;
use App\Models\CommercialPlan;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\CompanySubscription;
use App\Models\Property;
use App\Models\User;
use App\Services\Developer\CompanyCatalogWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class BillingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->withoutMiddleware(ThrottleRequests::class);
        config([
            'billing.enabled' => true,
            'billing.stripe.secret_key' => 'sk_test_ExampleOnly',
            'billing.stripe.webhook_secret' => 'whsec_ExampleOnly',
        ]);
    }

    private function company(string $slug = 'business'): Company
    {
        return Company::query()->create([
            'name' => 'Business '.$slug, 'slug' => $slug,
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
    }

    private function user(Company $company, string $role = 'owner'): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        CompanyMembership::query()->create([
            'company_id' => $company->id, 'user_id' => $user->id,
            'role' => $role, 'status' => 'active',
        ]);
        return $user;
    }

    private function plan(int $limit = 2): CommercialPlan
    {
        return CommercialPlan::query()->create([
            'slug' => 'pro', 'name' => 'Pro Plan', 'description' => 'Test features',
            'price_cents' => 1999, 'currency' => 'USD',
            'billing_period' => 'month', 'listing_limit' => $limit, 'is_active' => true,
        ]);
    }

    private function checkout(Company $company, User $owner, CommercialPlan $plan): BillingOrder
    {
        Http::fake([
            'api.stripe.com/*' => function ($request) {
                if (str_ends_with($request->url(), '/v1/refunds')) {
                    $amount = (int) ($request['amount'] ?? 0);
                    return Http::response([
                        'id' => 're_test_'.$amount,
                        'payment_intent' => 'pi_test_payment_001',
                        'amount' => $amount, 'currency' => 'usd', 'livemode' => false,
                    ]);
                }
                return Http::response([
                    'id' => 'cs_test_fixed123', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_fixed123',
                    'amount_total' => 1999, 'currency' => 'usd', 'mode' => 'payment', 'livemode' => false,
                ]);
            },
        ]);
        $this->actingAs($owner)->post(route('developer.finance.plans.checkout', [$company, $plan]))
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_fixed123');
        $order = BillingOrder::query()->firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertDatabaseHas('billing_invoices', [
            'billing_order_id' => $order->id, 'status' => 'open', 'total_cents' => 1999,
        ]);
        $this->assertDatabaseCount('company_subscriptions', 0);
        return $order;
    }

    private function signed(array $event, ?int $when = null): \Illuminate\Testing\TestResponse
    {
        $json = json_encode($event, JSON_THROW_ON_ERROR);
        $when ??= time();
        $signature = hash_hmac('sha256', $when.'.'.$json, 'whsec_ExampleOnly');

        return $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't='.$when.',v1='.$signature,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], $json);
    }

    private function event(BillingOrder $order, string $eventId = 'evt_payment_test123'): array
    {
        return [
            'id' => $eventId, 'type' => 'checkout.session.completed', 'livemode' => false,
            'data' => ['object' => [
                'object' => 'checkout.session',
                'id' => $order->gateway_session_id,
                'client_reference_id' => $order->reference,
                'metadata' => ['order_reference' => $order->reference],
                'payment_status' => 'paid', 'currency' => 'usd',
                'mode' => 'payment', 'livemode' => false,
                'amount_total' => $order->amount_cents,
                'payment_intent' => 'pi_test_payment_001',
            ]],
        ];
    }

    private function settle(BillingOrder $order): void
    {
        $this->signed($this->event($order))->assertOk();
        $this->assertDatabaseHas('billing_orders', ['id' => $order->id, 'status' => 'paid']);
    }

    private function refundEvent(BillingRefund $refund, string $status, string $id): array
    {
        return [
            'id' => $id, 'type' => 'refund.updated', 'livemode' => false,
            'data' => ['object' => [
                'object' => 'refund',
                'id' => $refund->gateway_refund_id,
                'payment_intent' => $refund->payment->gateway_payment_id,
                'currency' => 'usd', 'amount' => $refund->amount_cents,
                'livemode' => false, 'status' => $status,
            ]],
        ];
    }

    public function test_disabled_checkout_is_fail_closed_without_creating_an_order(): void
    {
        config(['billing.enabled' => false]);
        $company = $this->company();
        $owner = $this->user($company);
        $plan = $this->plan();
        $this->actingAs($owner)
            ->post(route('developer.finance.plans.checkout', [$company, $plan]))
            ->assertStatus(503);
        $this->assertDatabaseCount('billing_orders', 0);
        $this->signed($this->event(new BillingOrder()))->assertStatus(503);
    }

    public function test_only_approved_company_owner_can_start_checkout(): void
    {
        $company = $this->company();
        $plan = $this->plan();
        $other = $this->user($this->company('other'));
        $this->actingAs($other)
            ->post(route('developer.finance.plans.checkout', [$company, $plan]))
            ->assertForbidden();
        $member = $this->user($company, 'member');
        $this->actingAs($member)
            ->post(route('developer.finance.plans.checkout', [$company, $plan]))
            ->assertForbidden();
        $this->assertDatabaseCount('billing_orders', 0);
    }

    public function test_unsigned_stale_and_amount_mismatched_callbacks_cannot_grant_access(): void
    {
        $company = $this->company();
        $order = $this->checkout($company, $this->user($company), $this->plan());

        $payload = json_encode($this->event($order), JSON_THROW_ON_ERROR);
        $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1='.str_repeat('0', 64),
            'HTTP_ACCEPT' => 'application/json',
        ], $payload)->assertStatus(400);

        $this->signed($this->event($order), time() - 3600)->assertStatus(400);

        $wrongAmount = $this->event($order);
        $wrongAmount['data']['object']['amount_total'] = 100;
        $this->signed($wrongAmount)->assertStatus(409);
        $this->assertDatabaseCount('billing_payments', 0);
        $this->assertDatabaseCount('company_subscriptions', 0);
    }

    public function test_verified_paid_webhook_is_idempotent_and_grants_time_bounded_plan(): void
    {
        $company = $this->company();
        $order = $this->checkout($company, $this->user($company), $this->plan(1));

        $pending = $this->event($order, 'evt_pending_test123');
        $pending['data']['object']['payment_status'] = 'unpaid';
        $this->signed($pending)->assertOk();
        $this->assertDatabaseCount('billing_payments', 0);

        $this->settle($order);
        $this->signed($this->event($order))->assertOk();
        $this->signed($this->event($order, 'evt_same_charge_123'))->assertOk();

        $this->assertDatabaseCount('billing_payments', 1);
        $this->assertDatabaseCount('company_subscriptions', 1);
        $this->assertSame('paid', $order->fresh()->invoice->status);
        $subscription = CompanySubscription::query()->firstOrFail();
        $this->assertTrue($subscription->isCurrent());
        $this->assertSame(1, $subscription->listing_limit);
        $this->assertTrue($subscription->ends_at->gt(now()->addDays(27)));

        Property::query()->create([
            'company_id' => $company->id,
            'reference_code' => 'Q-ONE',
            'title' => 'Existing property',
            'property_type' => 'house',
            'status' => 'draft',
        ]);
        $this->expectException(ValidationException::class);
        app(CompanyCatalogWriter::class)->create($this->user($company), $company, [
            'reference_code' => 'Q-TWO',
            'title' => 'Quota should block this property',
            'property_type' => 'house',
            'transaction_type' => 'sale',
            'price' => 25000,
        ]);
    }

    public function test_verified_refunds_update_ledger_and_full_refund_revokes_entitlement(): void
    {
        $company = $this->company();
        $owner = $this->user($company);
        $order = $this->checkout($company, $owner, $this->plan());
        $this->settle($order);
        $admin = User::factory()->create(['system_role' => 'super_admin']);

        $payment = BillingPayment::query()->firstOrFail();
        $this->actingAs($admin)->post(route('admin.finance.billing.refund', $payment), [
            'amount' => '4.99', 'reason' => 'Customer requested adjustment',
        ])->assertRedirect();
        $partial = BillingRefund::query()->firstOrFail();
        $this->assertSame('submitted', $partial->status);
        $this->assertSame(0, $payment->fresh()->refunded_cents);
        $this->signed($this->refundEvent($partial, 'succeeded', 'evt_partial_test123'))->assertOk();
        $this->signed($this->refundEvent($partial, 'succeeded', 'evt_partial_test123'))->assertOk();
        $this->assertSame(499, $payment->fresh()->refunded_cents);
        $this->assertSame('partially_refunded', $order->fresh()->invoice->status);
        $this->assertTrue(CompanySubscription::query()->firstOrFail()->isCurrent());

        $this->post(route('admin.finance.billing.refund', $payment), [
            'amount' => '15.00', 'reason' => 'Refund remaining amount',
        ])->assertRedirect();
        $full = BillingRefund::query()->latest('id')->firstOrFail();
        $this->signed($this->refundEvent($full, 'succeeded', 'evt_full_test123'))->assertOk();

        $this->assertSame(1999, $payment->fresh()->refunded_cents);
        $this->assertSame('refunded', $order->fresh()->invoice->status);
        $this->assertFalse(CompanySubscription::query()->firstOrFail()->isCurrent());
        $this->assertDatabaseHas('audit_events', ['action' => 'billing.refund.verified']);
    }

    public function test_refund_limit_reserves_pending_requests_and_blocks_non_admin(): void
    {
        $company = $this->company();
        $order = $this->checkout($company, $this->user($company), $this->plan());
        $this->settle($order);
        $payment = BillingPayment::query()->firstOrFail();

        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
            ->post(route('admin.finance.billing.refund', $payment), [
                'amount' => '5.00', 'reason' => 'Unauthorized refund',
            ])->assertForbidden();
        $this->assertDatabaseCount('billing_refunds', 0);
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)->post(route('admin.finance.billing.refund', $payment), [
            'amount' => '15.00', 'reason' => 'Pending amount reserved',
        ])->assertRedirect();
        $this->post(route('admin.finance.billing.refund', $payment), [
            'amount' => '15.00', 'reason' => 'Try to double refund',
        ])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('billing_refunds', 1);
    }
}
