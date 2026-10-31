<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Models\BillingInvoice;
use App\Models\BillingOrder;
use App\Models\BillingPayment;
use App\Models\BillingRenewal;
use App\Models\BillingDispute;
use App\Models\CommercialPlan;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\CompanySubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class RecurringBillingOperationsTest extends TestCase
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
            'billing.stripe.secret_key' => 'sk_test_OnlyForTests',
            'billing.stripe.webhook_secret' => 'whsec_OnlyForTests',
        ]);
        Http::fake(['api.stripe.com/*' => Http::response([
            'id' => 'cs_test_recurring_001', 'livemode' => false,
            'mode' => 'subscription', 'amount_total' => 1999, 'currency' => 'usd',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_recurring_001',
        ])]);
    }

    private function company(string $slug = 'alpha'): Company
    {
        return Company::query()->create([
            'slug' => $slug, 'name' => 'Company '.$slug,
            'status' => 'approved', 'published_at' => now()->subDay(),
        ]);
    }

    private function owner(Company $company): User
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        CompanyMembership::query()->create([
            'company_id' => $company->id, 'user_id' => $owner->id,
            'role' => 'owner', 'status' => 'active',
        ]);
        return $owner;
    }

    private function checkout(): array
    {
        $company = $this->company();
        $owner = $this->owner($company);
        $plan = CommercialPlan::query()->create([
            'slug' => 'monthly', 'name' => 'Monthly Business',
            'price_cents' => 1999, 'currency' => 'USD', 'billing_period' => 'month',
            'listing_limit' => 5, 'is_active' => true,
        ]);
        $this->actingAs($owner)->post(route('developer.finance.plans.checkout', [$company, $plan]))
            ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_recurring_001');
        $order = BillingOrder::query()->firstOrFail();
        $this->assertSame('subscription', $order->checkout_mode);
        $this->assertDatabaseCount('company_subscriptions', 0);
        return [$company, $owner, $order];
    }

    private function signed(string $id, string $type, array $object): \Illuminate\Testing\TestResponse
    {
        $body = json_encode([
            'id' => $id, 'type' => $type, 'livemode' => false, 'data' => ['object' => $object],
        ], JSON_THROW_ON_ERROR);
        $now = time();
        return $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't='.$now.',v1='.hash_hmac('sha256', $now.'.'.$body, 'whsec_OnlyForTests'),
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    private function checkoutSession(BillingOrder $order): array
    {
        return [
            'object' => 'checkout.session', 'id' => $order->gateway_session_id,
            'client_reference_id' => $order->reference,
            'metadata' => ['order_reference' => $order->reference],
            'payment_status' => 'paid', 'mode' => 'subscription', 'livemode' => false,
            'currency' => 'usd', 'amount_total' => 1999,
            'subscription' => 'sub_test_recurring01',
        ];
    }

    private function invoice(string $id, string $intent, string $reason, int $start, int $end): array
    {
        return [
            'object' => 'invoice', 'id' => $id, 'livemode' => false,
            'subscription' => 'sub_test_recurring01', 'customer' => 'cus_test_001',
            'status' => 'paid', 'paid' => true, 'paid_out_of_band' => false,
            'amount_paid' => 1999, 'currency' => 'usd', 'billing_reason' => $reason,
            'payment_intent' => $intent,
            'lines' => ['data' => [[
                'subscription' => 'sub_test_recurring01', 'proration' => false,
                'period' => ['start' => $start, 'end' => $end],
            ]]],
        ];
    }

    private function paid(): array
    {
        [$company, $owner, $order] = $this->checkout();
        $this->signed('evt_recurring_session_01', 'checkout.session.completed', $this->checkoutSession($order))
            ->assertOk();
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertDatabaseCount('company_subscriptions', 0);
        $start = time() - 60;
        $end = time() + 30 * 86400;
        $this->signed('evt_recurring_paid_001', 'invoice.paid',
            $this->invoice('in_test_initial_001', 'pi_test_cycle001', 'subscription_create', $start, $end))
            ->assertOk();
        $this->assertSame('paid', $order->fresh()->status);
        return [$company, $owner, $order, $start, $end];
    }

    public function test_checkout_and_signed_invoice_are_two_distinct_steps(): void
    {
        [$company, $owner, $order, $start, $end] = $this->paid();
        $this->assertDatabaseCount('billing_payments', 1);
        $this->assertDatabaseCount('billing_renewals', 1);
        $this->assertDatabaseHas('billing_tax_reviews', ['status' => 'unreviewed']);
        $current = CompanySubscription::query()->firstOrFail();
        $this->assertTrue($current->isCurrent());
        $this->assertSame('sub_test_recurring01', $current->gateway_subscription_id);
        $this->assertSame('cus_test_001', $current->gateway_customer_id);
        $this->get(route('developer.finance.receipts.show', [$company, $order->invoice]))
            ->assertOk()->assertSee('NOT a tax invoice');
        $this->artisan('billing:audit --json')->assertExitCode(0);
    }

    public function test_verified_renewal_creates_new_immutable_ledger_and_is_idempotent(): void
    {
        [$company, $owner, $order, $start, $end] = $this->paid();
        $renewal = $this->invoice('in_test_renewal002', 'pi_test_cycle002',
            'subscription_cycle', $end, $end + 30 * 86400);
        $this->signed('evt_recurring_renewal02', 'invoice.paid', $renewal)->assertOk();
        $this->signed('evt_recurring_renewal02', 'invoice.paid', $renewal)->assertOk();
        $this->signed('evt_recurring_renewal03', 'invoice.paid', $renewal)->assertOk();
        $this->assertDatabaseCount('billing_orders', 2);
        $this->assertDatabaseCount('billing_invoices', 2);
        $this->assertDatabaseCount('billing_payments', 2);
        $this->assertDatabaseCount('billing_renewals', 2);
        $current = CompanySubscription::query()->firstOrFail();
        $this->assertNotSame($order->id, $current->billing_order_id);
        $this->assertTrue($current->ends_at->gt(now()->addDays(50)));
    }

    public function test_paid_out_of_band_or_wrong_amount_cannot_extend_access(): void
    {
        [$company, $owner, $order, $start, $end] = $this->paid();
        $bad = $this->invoice('in_bad_003', 'pi_bad_003', 'subscription_cycle', $end, $end + 30 * 86400);
        $bad['paid_out_of_band'] = true;
        $this->signed('evt_out_of_band_003', 'invoice.paid', $bad)->assertStatus(409);
        $bad['paid_out_of_band'] = false;
        $bad['amount_paid'] = 1;
        $this->signed('evt_wrong_amount_004', 'invoice.paid', $bad)->assertStatus(409);
        $this->assertDatabaseCount('billing_orders', 1);
        $this->assertDatabaseCount('billing_payments', 1);
    }

    public function test_failed_renewal_suspends_and_only_paid_invoice_restores(): void
    {
        [$company, $owner, $order, $start, $end] = $this->paid();
        $failure = [
            'object' => 'invoice', 'livemode' => false,
            'subscription' => 'sub_test_recurring01', 'customer' => 'cus_test_001',
        ];
        $this->signed('evt_failed_invoice_001', 'invoice.payment_failed', $failure)->assertOk();
        $this->assertSame('past_due', CompanySubscription::query()->firstOrFail()->status);
        $paid = $this->invoice('in_restored_004', 'pi_restore_004',
            'subscription_cycle', $end, $end + 30 * 86400);
        $this->signed('evt_paid_restored_004', 'invoice.paid', $paid)->assertOk();
        $this->assertTrue(CompanySubscription::query()->firstOrFail()->isCurrent());
        $this->assertDatabaseCount('billing_renewals', 2);
    }

    public function test_subscription_cancellation_cannot_be_reactivated_by_later_renewal(): void
    {
        [$company, $owner, $order, $start, $end] = $this->paid();
        $sub = [
            'object' => 'subscription', 'id' => 'sub_test_recurring01',
            'customer' => 'cus_test_001', 'status' => 'canceled', 'livemode' => false,
        ];
        $this->signed('evt_sub_canceled_001', 'customer.subscription.deleted', $sub)->assertOk();
        $this->assertFalse(CompanySubscription::query()->firstOrFail()->isCurrent());
        $this->signed('evt_post_cancel_002', 'invoice.paid', $this->invoice(
            'in_cancelled_002', 'pi_cancelled_002', 'subscription_cycle', $end, $end + 30 * 86400,
        ))->assertStatus(409);
        $this->assertDatabaseCount('billing_renewals', 1);
    }

    public function test_dispute_suspends_and_verified_won_status_restores_but_lost_revokes(): void
    {
        [$company, $owner, $order, $start, $end] = $this->paid();
        $dispute = [
            'object' => 'dispute', 'id' => 'dp_test_001', 'charge' => 'ch_test_001',
            'payment_intent' => 'pi_test_cycle001',
            'amount' => 1999, 'currency' => 'usd', 'livemode' => false,
            'status' => 'needs_response',
        ];
        $this->signed('evt_dispute_created_01', 'charge.dispute.created', $dispute)->assertOk();
        $this->assertSame('suspended', CompanySubscription::query()->firstOrFail()->status);
        $dispute['status'] = 'won';
        $this->signed('evt_dispute_closed_01', 'charge.dispute.closed', $dispute)->assertOk();
        $this->assertTrue(CompanySubscription::query()->firstOrFail()->isCurrent());
        $dispute['status'] = 'needs_response';
        $this->signed('evt_stale_dispute_01', 'charge.dispute.updated', $dispute)->assertOk();
        $this->assertTrue(CompanySubscription::query()->firstOrFail()->isCurrent());

        $dispute['id'] = 'dp_test_002';
        $dispute['charge'] = 'ch_test_002';
        $dispute['status'] = 'lost';
        $this->signed('evt_dispute_closed_02', 'charge.dispute.closed', $dispute)->assertOk();
        $this->assertFalse(CompanySubscription::query()->firstOrFail()->isCurrent());
        $this->assertDatabaseCount('billing_disputes', 2);
    }

    public function test_one_won_dispute_cannot_reactivate_a_company_with_another_open_dispute(): void
    {
        [$company, $owner, $order, $start, $end] = $this->paid();
        $first = [
            'object' => 'dispute', 'id' => 'dp_open_a', 'charge' => 'ch_open_a',
            'payment_intent' => 'pi_test_cycle001',
            'amount' => 1200, 'currency' => 'usd',
            'livemode' => false, 'status' => 'needs_response',
        ];
        $second = array_replace($first, ['id' => 'dp_open_b', 'charge' => 'ch_open_b', 'amount' => 799]);
        $this->signed('evt_open_a', 'charge.dispute.created', $first)->assertOk();
        $this->signed('evt_open_b', 'charge.dispute.created', $second)->assertOk();
        $first['status'] = 'won';
        $this->signed('evt_win_a', 'charge.dispute.closed', $first)->assertOk();
        $this->assertSame('suspended', CompanySubscription::query()->firstOrFail()->status);

        $second['status'] = 'won';
        $this->signed('evt_win_b', 'charge.dispute.closed', $second)->assertOk();
        $this->assertTrue(CompanySubscription::query()->firstOrFail()->isCurrent());
    }

    public function test_unmatched_dispute_is_flagged_for_manual_reconciliation(): void
    {
        $dispute = [
            'object' => 'dispute', 'id' => 'dp_unmatched_001',
            'charge' => 'ch_unknown_001', 'amount' => 1500,
            'currency' => 'usd', 'status' => 'needs_response', 'livemode' => false,
        ];
        $this->signed('evt_unmatched_dispute_01', 'charge.dispute.created', $dispute)->assertOk();
        $this->assertTrue(BillingDispute::query()->firstOrFail()->needs_reconciliation);
        $admin = User::factory()->create(['system_role' => 'super_admin']);
        $this->actingAs($admin)->get(route('admin.finance.reconciliation.index'))
            ->assertOk()->assertSee('Unmatched payment');
    }

    public function test_payment_confirmation_is_tenant_isolated_and_not_tax_invoice(): void
    {
        [$company, $owner, $order, $start, $end] = $this->paid();
        $other = $this->owner($this->company('other'));
        $this->actingAs($other)->get(route('developer.finance.receipts.show', [$company, $order->invoice]))
            ->assertForbidden();
        $this->actingAs($owner)->get(route('developer.finance.receipts.show', [$company, $order->invoice]))
            ->assertOk()->assertSee('NOT a tax invoice')->assertDontSee('VAT invoice');
    }
}
