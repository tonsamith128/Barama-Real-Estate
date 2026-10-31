<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingDispute;
use App\Models\BillingPayment;
use App\Models\CompanySubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BillingDisputeProcessor
{
    private function reject(): never
    {
        throw ValidationException::withMessages(['dispute' => 'Dispute data is incomplete or inconsistent.']);
    }

    public function process(array $object, string $eventType): void
    {
        $id = $object['id'] ?? null;
        $intent = $object['payment_intent'] ?? null;
        $charge = $object['charge'] ?? null;
        $amount = $object['amount'] ?? null;
        $currency = $object['currency'] ?? null;
        $status = $object['status'] ?? null;
        if (($object['object'] ?? null) !== 'dispute'
            || ($object['livemode'] ?? true) !== false
            || !is_string($id) || !str_starts_with($id, 'dp_')
            || !is_string($charge) || !str_starts_with($charge, 'ch_')
            || !is_int($amount) || $amount < 1
            || !is_string($currency) || !preg_match('/^[a-z]{3}$/', $currency)
            || !is_string($status) || strlen($status) > 32) {
            $this->reject();
        }
        $existing = BillingDispute::query()->where('gateway_dispute_id', $id)
            ->lockForUpdate()->first();
        $payment = is_string($intent) && str_starts_with($intent, 'pi_')
            ? BillingPayment::query()->where('gateway_payment_id', $intent)
                ->lockForUpdate()->first()
            : null;
        if ($payment && ($payment->currency !== strtoupper($currency) || $amount > $payment->amount_cents)) {
            $this->reject();
        }
        if ($existing !== null && ($existing->amount_cents !== $amount
            || $existing->currency !== strtoupper($currency)
            || $existing->gateway_charge_id !== $charge)) {
            $this->reject();
        }
        $row = $existing ?? BillingDispute::query()->create([
            'gateway_dispute_id' => $id, 'gateway_charge_id' => $charge,
            'gateway_payment_id' => is_string($intent) ? $intent : null,
            'billing_payment_id' => $payment?->getKey(),
            'amount_cents' => $amount, 'currency' => strtoupper($currency),
            'status' => 'open', 'needs_reconciliation' => $payment === null,
        ]);
        // Never let a late "updated" event reopen a terminal dispute.
        if (in_array($row->status, ['won', 'lost'], true)) {
            return;
        }
        $closed = $eventType === 'charge.dispute.closed';
        $terminal = $closed && in_array($status, ['won', 'lost'], true);
        $newStatus = $terminal ? $status : 'open';
        $row->update([
            'billing_payment_id' => $payment?->getKey() ?? $row->billing_payment_id,
            'gateway_payment_id' => is_string($intent) ? $intent : $row->gateway_payment_id,
            'status' => $newStatus,
            'needs_reconciliation' => $payment === null,
            'resolved_at' => $terminal ? now() : null,
        ]);
        if ($payment !== null) {
            // A dispute against an older recurring invoice still affects
            // the current cycle of that exact provider subscription.
            $order = $payment->order;
            $subscription = CompanySubscription::query()
                ->where('company_id', $order->company_id)
                ->lockForUpdate()->first();
            if ($subscription !== null && $subscription->billing_order_id !== $order->getKey()
                && ($order->gateway_subscription_id === null
                    || $subscription->gateway_subscription_id !== $order->gateway_subscription_id)) {
                $subscription = null;
            }
            if ($subscription !== null) {
                if ($newStatus === 'lost') {
                    $subscription->update(['status' => 'revoked', 'ends_at' => now()]);
                } elseif ($newStatus === 'won') {
                    if ($subscription->status === 'suspended'
                        && $subscription->ends_at?->isFuture()
                        && $payment->refunded_cents < $payment->amount_cents) {
                        $subscription->update(['status' => 'active']);
                    }
                } elseif ($subscription->status === 'active') {
                    $subscription->update(['status' => 'suspended']);
                }
            }
        }
        DB::table('audit_events')->insert([
            'actor_id' => null, 'action' => 'billing.dispute.recorded',
            'subject_type' => 'billing_dispute', 'subject_id' => $row->getKey(),
            'context' => json_encode(['status' => $newStatus, 'matched' => $payment !== null], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
