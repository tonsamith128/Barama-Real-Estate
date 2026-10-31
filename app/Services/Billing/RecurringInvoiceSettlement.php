<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingInvoice;
use App\Models\BillingOrder;
use App\Models\BillingPayment;
use App\Models\BillingRenewal;
use App\Models\BillingTaxReview;
use App\Models\Company;
use App\Models\CompanySubscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RecurringInvoiceSettlement
{
    private function reject(string $reason): never
    {
        throw ValidationException::withMessages(['invoice' => $reason]);
    }

    public function settle(array $invoice): void
    {
        // Never accept an out-of-band/zero-value credit or a browser redirect as
        // payment. A genuine processor PaymentIntent and billing period must exist.
        $subscriptionId = $invoice['subscription']
            ?? ($invoice['parent']['subscription_details']['subscription'] ?? null);
        $providerInvoice = $invoice['id'] ?? null;
        $intent = $invoice['payment_intent'] ?? null;
        $customerId = $invoice['customer'] ?? null;
        if (($invoice['object'] ?? null) !== 'invoice'
            || ($invoice['livemode'] ?? true) !== false
            || ($invoice['status'] ?? null) !== 'paid'
            || ($invoice['paid'] ?? null) !== true
            || ($invoice['paid_out_of_band'] ?? true) !== false
            || !is_string($providerInvoice) || !str_starts_with($providerInvoice, 'in_')
            || !is_string($subscriptionId) || !str_starts_with($subscriptionId, 'sub_')
            || !is_string($intent) || !str_starts_with($intent, 'pi_')
            || !is_string($customerId) || !str_starts_with($customerId, 'cus_')
            || ($invoice['currency'] ?? null) !== 'usd'
            || !is_int($invoice['amount_paid'] ?? null) || $invoice['amount_paid'] < 1
            || ($invoice['billing_reason'] ?? null) !== 'subscription_create'
                && ($invoice['billing_reason'] ?? null) !== 'subscription_cycle') {
            $this->reject('Paid recurring invoice evidence is incomplete.');
        }

        $period = null;
        foreach (($invoice['lines']['data'] ?? []) as $line) {
            $lineSubscription = $line['subscription']
                ?? ($line['parent']['subscription_item_details']['subscription'] ?? null);
            if ($lineSubscription === $subscriptionId
                && is_int($line['period']['start'] ?? null)
                && is_int($line['period']['end'] ?? null)
                && ($line['proration'] ?? false) === false) {
                $period = $line['period'];
                break;
            }
        }
        if ($period === null || $period['start'] < 1
            || $period['end'] <= $period['start']
            || $period['end'] - $period['start'] > 400 * 86400
            || $period['end'] > time() + 405 * 86400) {
            $this->reject('Invoice has no acceptable subscription service period.');
        }
        $startsAt = Carbon::createFromTimestamp($period['start']);
        $endsAt = Carbon::createFromTimestamp($period['end']);

        // The checkout-completed event may arrive after invoice.paid. Reject
        // and let Stripe retry instead of guessing the owning company.
        $initial = BillingOrder::query()
            ->where('gateway_subscription_id', $subscriptionId)
            ->where('checkout_mode', 'subscription')
            ->orderBy('id')->first();
        if ($initial === null) {
            $this->reject('Subscription has no verified local checkout binding.');
        }
        Company::query()->whereKey($initial->company_id)->lockForUpdate()->firstOrFail();
        $initial = BillingOrder::query()->whereKey($initial->getKey())->lockForUpdate()->firstOrFail();
        if ($initial->currency !== 'USD' || $initial->amount_cents !== $invoice['amount_paid']
            || $initial->gateway !== 'stripe_test') {
            $this->reject('Recurring amount or gateway mismatches local contract.');
        }
        if (BillingRenewal::query()->where('gateway_invoice_id', $providerInvoice)->exists()) {
            return;
        }
        if (BillingPayment::query()->where('gateway_payment_id', $intent)->exists()) {
            $this->reject('PaymentIntent is already assigned to another invoice.');
        }

        $current = CompanySubscription::query()->where('company_id', $initial->company_id)
            ->lockForUpdate()->first();
        if ($initial->status === 'pending') {
            if ($current !== null && $current->billing_order_id > $initial->getKey()) {
                $this->reject('A newer entitlement already supersedes this checkout.');
            }
            $order = $initial;
            $invoiceRow = BillingInvoice::query()->where('billing_order_id', $order->getKey())
                ->lockForUpdate()->firstOrFail();
            if ($invoiceRow->status !== 'open' || $invoiceRow->total_cents !== $invoice['amount_paid']) {
                $this->reject('Initial invoice is not open for this amount.');
            }
        } else {
            if ($initial->status !== 'paid' || !$current
                || $current->gateway_subscription_id !== $subscriptionId
                || $current->gateway_customer_id !== $customerId
                || !in_array($current->status, ['active', 'past_due'], true)
                || $current->billing_order_id < $initial->getKey()
                || $current->ends_at === null
                || $endsAt->lte($current->ends_at)) {
                $this->reject('Renewal cannot extend an unverified or revoked subscription.');
            }
            if (($invoice['billing_reason'] ?? null) !== 'subscription_cycle') {
                $this->reject('Only a regular billing cycle may renew the subscription.');
            }
            $order = BillingOrder::query()->create([
                'company_id' => $initial->company_id,
                'commercial_plan_id' => $initial->commercial_plan_id,
                'requested_by_user_id' => $initial->requested_by_user_id,
                'reference' => (string) Str::uuid(),
                'gateway' => 'stripe_test', 'checkout_mode' => 'subscription',
                'gateway_subscription_id' => $subscriptionId,
                'gateway_payment_id' => $intent, 'status' => 'paid',
                'plan_name' => $initial->plan_name,
                'amount_cents' => $initial->amount_cents,
                'currency' => 'USD', 'billing_period' => $initial->billing_period,
                'listing_limit' => $initial->listing_limit, 'paid_at' => now(),
            ]);
            $invoiceRow = BillingInvoice::query()->create([
                'billing_order_id' => $order->getKey(),
                'invoice_number' => 'BRM-'.str_pad((string) $order->getKey(), 8, '0', STR_PAD_LEFT),
                'status' => 'paid', 'total_cents' => $invoice['amount_paid'],
                'currency' => 'USD', 'paid_at' => now(),
            ]);
        }
        if ($initial->status === 'pending') {
            $order->update(['status' => 'paid', 'gateway_payment_id' => $intent, 'paid_at' => now()]);
            $invoiceRow->update(['status' => 'paid', 'paid_at' => now()]);
        }
        BillingPayment::query()->create([
            'billing_order_id' => $order->getKey(),
            'gateway' => 'stripe_test', 'gateway_payment_id' => $intent,
            'amount_cents' => $invoice['amount_paid'], 'currency' => 'USD',
            'status' => 'paid', 'paid_at' => now(),
        ]);
        BillingRenewal::query()->create([
            'billing_order_id' => $order->getKey(),
            'gateway_invoice_id' => $providerInvoice,
            'gateway_subscription_id' => $subscriptionId,
            'period_start' => $startsAt, 'period_end' => $endsAt,
        ]);
        BillingTaxReview::query()->firstOrCreate(
            ['billing_invoice_id' => $invoiceRow->getKey()], ['status' => 'unreviewed'],
        );
        CompanySubscription::query()->updateOrCreate(
            ['company_id' => $initial->company_id],
            [
                'commercial_plan_id' => $initial->commercial_plan_id,
                'billing_order_id' => $order->getKey(),
                'status' => 'active', 'billing_period' => $initial->billing_period,
                'listing_limit' => $initial->listing_limit,
                // A paid renewal extends expiry without moving the original
                // service start into the future and accidentally denying access.
                'starts_at' => $current !== null
                    && $current->gateway_subscription_id === $subscriptionId
                    ? $current->starts_at : $startsAt,
                'ends_at' => $endsAt,
                'gateway_subscription_id' => $subscriptionId,
                'gateway_customer_id' => $customerId,
            ],
        );
        DB::table('audit_events')->insert([
            'actor_id' => null, 'action' => 'billing.renewal.verified',
            'subject_type' => 'billing_order', 'subject_id' => $order->getKey(),
            'context' => json_encode(['gateway_invoice_id' => $providerInvoice, 'amount_cents' => $invoice['amount_paid']], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
