<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\CompanySubscription;
use Illuminate\Validation\ValidationException;

final class SubscriptionSignalProcessor
{
    private function reject(): never
    {
        throw ValidationException::withMessages(['subscription' => 'Unmatched subscription event.']);
    }

    public function paymentFailed(array $invoice): void
    {
        $subscriptionId = $invoice['subscription']
            ?? ($invoice['parent']['subscription_details']['subscription'] ?? null);
        if (($invoice['object'] ?? null) !== 'invoice'
            || ($invoice['livemode'] ?? true) !== false
            || !is_string($subscriptionId) || !str_starts_with($subscriptionId, 'sub_')
            || !is_string($invoice['customer'] ?? null)) {
            $this->reject();
        }
        $sub = CompanySubscription::query()
            ->where('gateway_subscription_id', $subscriptionId)
            ->lockForUpdate()->first();
        if (!$sub || $sub->gateway_customer_id !== $invoice['customer']) {
            $this->reject();
        }
        if ($sub->status === 'active') {
            $sub->update(['status' => 'past_due']);
        }
    }

    public function subscriptionChanged(array $object, string $eventType): void
    {
        if (($object['object'] ?? null) !== 'subscription'
            || ($object['livemode'] ?? true) !== false
            || !is_string($object['id'] ?? null)
            || !str_starts_with($object['id'], 'sub_')
            || !is_string($object['customer'] ?? null)) {
            $this->reject();
        }
        $sub = CompanySubscription::query()->where('gateway_subscription_id', $object['id'])
            ->lockForUpdate()->first();
        if (!$sub || $sub->gateway_customer_id !== $object['customer']) {
            $this->reject();
        }
        if ($eventType === 'customer.subscription.deleted'
            || in_array($object['status'] ?? '', ['canceled', 'unpaid', 'incomplete_expired'], true)) {
            if (!in_array($sub->status, ['revoked'], true)) {
                $sub->update(['status' => 'revoked', 'ends_at' => now()]);
            }
            return;
        }
        if (($object['cancel_at_period_end'] ?? false) === true
            && !$sub->cancel_at_period_end) {
            $sub->update(['cancel_at_period_end' => true, 'cancellation_requested_at' => now()]);
        }
        if (in_array($object['status'] ?? '', ['past_due', 'paused'], true)
            && $sub->status === 'active') {
            $sub->update(['status' => 'past_due']);
        }
        // An updated subscription with status "active" is not proof of payment.
        // Only a matched, paid, non-out-of-band invoice may restore access.
    }
}
