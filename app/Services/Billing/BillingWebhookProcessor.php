<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingInvoice;
use App\Models\BillingOrder;
use App\Models\BillingPayment;
use App\Models\BillingRefund;
use App\Models\BillingWebhookEvent;
use App\Models\Company;
use App\Models\CompanySubscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BillingWebhookProcessor
{
    public function process(array $event): void
    {
        DB::transaction(function () use ($event): void {
            DB::table('billing_webhook_events')->insertOrIgnore([
                'gateway' => 'stripe_test',
                'gateway_event_id' => $event['id'],
                'event_type' => substr($event['type'], 0, 120),
                'status' => 'received',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $receipt = BillingWebhookEvent::query()
                ->where('gateway', 'stripe_test')
                ->where('gateway_event_id', $event['id'])
                ->lockForUpdate()->firstOrFail();
            if ($receipt->status === 'processed' || $receipt->status === 'ignored') {
                return;
            }

            $type = $event['type'];
            $object = $event['data']['object'];
            if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
                $this->settleCheckout($object);
            } elseif (in_array($type, ['refund.created', 'refund.updated'], true)) {
                $this->settleRefund($object);
            } else {
                $receipt->update(['status' => 'ignored', 'processed_at' => now()]);
                return;
            }
            $receipt->update(['status' => 'processed', 'processed_at' => now()]);
        }, 3);
    }

    private function reject(string $reason): never
    {
        throw ValidationException::withMessages(['webhook' => $reason]);
    }

    private function settleCheckout(array $session): void
    {
        if (($session['object'] ?? null) !== 'checkout.session'
            || !is_string($session['id'] ?? null)
            || !is_string($session['client_reference_id'] ?? null)) {
            $this->reject('Unexpected checkout object.');
        }

        // A successful redirect, a checkout completion without paid status,
        // and asynchronous pending payments must NEVER grant entitlements.
        if (($session['payment_status'] ?? null) !== 'paid') {
            return;
        }
        $order = BillingOrder::query()
            ->where('reference', $session['client_reference_id'])
            ->lockForUpdate()->first();
        if (!$order || $order->gateway_session_id !== $session['id']
            || $order->gateway !== 'stripe_test'
            || ($session['metadata']['order_reference'] ?? null) !== $order->reference
            || ($session['mode'] ?? null) !== 'payment'
            || ($session['currency'] ?? null) !== strtolower($order->currency)
            || ($session['livemode'] ?? true) !== false
            || !is_int($session['amount_total'] ?? null)
            || $session['amount_total'] !== $order->amount_cents
            || !is_string($session['payment_intent'] ?? null)
            || !str_starts_with($session['payment_intent'], 'pi_')) {
            $this->reject('Checkout does not match a verified local order.');
        }

        if ($order->status === 'paid') {
            if ($order->gateway_payment_id !== $session['payment_intent']) {
                $this->reject('Payment reference conflict.');
            }
            return;
        }
        if ($order->status !== 'pending') {
            $this->reject('Order is not awaiting payment.');
        }

        $invoice = BillingInvoice::query()->where('billing_order_id', $order->getKey())
            ->lockForUpdate()->firstOrFail();
        if ($invoice->status !== 'open' || $invoice->total_cents !== $order->amount_cents) {
            $this->reject('Invoice amount or state mismatch.');
        }

        $paidAt = now();
        $order->update([
            'status' => 'paid', 'gateway_payment_id' => $session['payment_intent'],
            'paid_at' => $paidAt,
        ]);
        $invoice->update(['status' => 'paid', 'paid_at' => $paidAt]);
        BillingPayment::query()->create([
            'billing_order_id' => $order->getKey(),
            'gateway' => 'stripe_test',
            'gateway_payment_id' => $session['payment_intent'],
            'amount_cents' => $order->amount_cents,
            'currency' => $order->currency,
            'status' => 'paid', 'paid_at' => $paidAt,
        ]);

        Company::query()->whereKey($order->company_id)->lockForUpdate()->firstOrFail();
        $current = CompanySubscription::query()->where('company_id', $order->company_id)
            ->lockForUpdate()->first();
        // Late webhooks for earlier orders may record payment but may not
        // downgrade or roll back a newer entitlement.
        if ($current === null || $order->getKey() > $current->billing_order_id) {
            $start = $paidAt->copy();
            if ($current?->isCurrent()
                && $current->commercial_plan_id === $order->commercial_plan_id
                && $current->ends_at !== null && $current->ends_at->gt($start)) {
                $start = $current->ends_at->copy();
            }
            $endsAt = match ($order->billing_period) {
                'month' => $start->copy()->addMonthNoOverflow(),
                'year' => $start->copy()->addYearNoOverflow(),
                'once' => null,
                default => $this->reject('Invalid billing period.'),
            };

            CompanySubscription::query()->updateOrCreate(
                ['company_id' => $order->company_id],
                [
                    'commercial_plan_id' => $order->commercial_plan_id,
                    'billing_order_id' => $order->getKey(),
                    'status' => 'active',
                    'billing_period' => $order->billing_period,
                    'listing_limit' => $order->listing_limit,
                    'starts_at' => $paidAt,
                    'ends_at' => $endsAt,
                ],
            );
        }

        $this->audit('billing.payment.verified', 'billing_order', $order->getKey(), [
            'payment_id' => $session['payment_intent'],
            'amount_cents' => $order->amount_cents,
        ]);
    }

    private function settleRefund(array $object): void
    {
        if (($object['object'] ?? null) !== 'refund'
            || !is_string($object['id'] ?? null)) {
            $this->reject('Unexpected refund object.');
        }
        $refund = BillingRefund::query()->where('gateway_refund_id', $object['id'])
            ->lockForUpdate()->first();
        if ($refund === null) {
            // Refund could have arrived before the API response was committed.
            $this->reject('Refund has not been matched to a local request.');
        }
        $payment = BillingPayment::query()->whereKey($refund->billing_payment_id)
            ->lockForUpdate()->firstOrFail();

        if (($object['livemode'] ?? true) !== false
            || ($object['payment_intent'] ?? null) !== $payment->gateway_payment_id
            || ($object['currency'] ?? null) !== strtolower($payment->currency)
            || !is_int($object['amount'] ?? null)
            || $object['amount'] !== $refund->amount_cents) {
            $this->reject('Refund amount or payment reference mismatch.');
        }

        if ($refund->status === 'succeeded') {
            return;
        }
        $status = $object['status'] ?? null;
        if ($status === 'failed' || $status === 'canceled') {
            $refund->update(['status' => 'failed']);
            $this->audit('billing.refund.failed', 'billing_refund', $refund->getKey(), []);
            return;
        }
        if ($status !== 'succeeded') {
            return;
        }

        if ($refund->status !== 'submitted') {
            $this->reject('Refund is not awaiting provider confirmation.');
        }

        $newTotal = $payment->refunded_cents + $refund->amount_cents;
        if ($newTotal > $payment->amount_cents) {
            $this->reject('Refund would exceed captured amount.');
        }
        $order = BillingOrder::query()->findOrFail($payment->billing_order_id);
        $invoice = BillingInvoice::query()->where('billing_order_id', $order->getKey())
            ->lockForUpdate()->firstOrFail();
        $status = $newTotal === $payment->amount_cents ? 'refunded' : 'partially_refunded';
        $payment->update(['refunded_cents' => $newTotal, 'status' => $status]);
        $invoice->update(['refunded_cents' => $newTotal, 'status' => $status]);
        $refund->update(['status' => 'succeeded', 'succeeded_at' => now()]);

        if ($status === 'refunded') {
            CompanySubscription::query()->where('company_id', $order->company_id)
                ->where('billing_order_id', $order->getKey())
                ->update(['status' => 'revoked', 'ends_at' => now()]);
        }
        $this->audit('billing.refund.verified', 'billing_refund', $refund->getKey(), [
            'amount_cents' => $refund->amount_cents, 'payment_id' => $payment->getKey(),
        ]);
    }

    private function audit(string $action, string $type, int $id, array $context): void
    {
        DB::table('audit_events')->insert([
            'actor_id' => null, 'action' => $action,
            'subject_type' => $type, 'subject_id' => $id,
            'context' => json_encode($context, JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
