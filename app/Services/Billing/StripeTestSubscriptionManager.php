<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\CompanySubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class StripeTestSubscriptionManager
{
    private function key(): string
    {
        $key = (string) config('billing.stripe.secret_key');
        if (!str_starts_with($key, 'sk_test_')) {
            throw new RuntimeException('Test-only Stripe API credentials are required.');
        }
        abort_unless(config('billing.enabled') && config('billing.recurring_test_enabled')
            && config('billing.subscription_management_test_enabled'), 503, 'Test subscription management disabled.');
        return $key;
    }

    private function assertEligible(CompanySubscription $subscription): void
    {
        abort_unless(in_array($subscription->status, ['active', 'past_due', 'suspended'], true)
            && $subscription->ends_at !== null && $subscription->ends_at->isFuture()
            && str_starts_with((string) $subscription->gateway_subscription_id, 'sub_')
            && str_starts_with((string) $subscription->gateway_customer_id, 'cus_'), 409);
    }

    public function cancelAtPeriodEnd(CompanySubscription $subscription, User $actor): void
    {
        $key = $this->key();
        $this->assertEligible($subscription);
        if ($subscription->cancel_at_period_end) {
            return;
        }

        $response = Http::asForm()->withToken($key)
            ->withHeaders(['Idempotency-Key' => 'barama-cancel-'.$subscription->getKey()])
            ->timeout(15)
            ->post('https://api.stripe.com/v1/subscriptions/'.$subscription->gateway_subscription_id, [
                'cancel_at_period_end' => 'true',
                'proration_behavior' => 'none',
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Stripe test subscription cancellation could not be confirmed.');
        }
        $data = $response->json();
        if (!is_array($data) || ($data['object'] ?? null) !== 'subscription'
            || ($data['livemode'] ?? true) !== false
            || ($data['id'] ?? null) !== $subscription->gateway_subscription_id
            || ($data['customer'] ?? null) !== $subscription->gateway_customer_id
            || ($data['cancel_at_period_end'] ?? null) !== true) {
            throw new RuntimeException('Stripe cancellation response did not match the subscription.');
        }
        DB::transaction(function () use ($subscription, $actor): void {
            $locked = CompanySubscription::query()->lockForUpdate()->findOrFail($subscription->getKey());
            abort_unless($locked->gateway_subscription_id === $subscription->gateway_subscription_id
                && $locked->gateway_customer_id === $subscription->gateway_customer_id
                && in_array($locked->status, ['active', 'past_due', 'suspended'], true), 409);
            if ($locked->cancel_at_period_end) {
                return;
            }
            $locked->update([
                'cancel_at_period_end' => true,
                'cancellation_requested_at' => now(),
            ]);
            DB::table('audit_events')->insert([
                'actor_id' => $actor->getKey(), 'action' => 'billing.subscription.cancel_scheduled',
                'subject_type' => 'company_subscription', 'subject_id' => $locked->getKey(),
                'context' => json_encode(['company_id' => $locked->company_id], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function portalUrl(CompanySubscription $subscription, string $returnUrl): string
    {
        $key = $this->key();
        $this->assertEligible($subscription);
        abort_unless(config('billing.stripe.portal_test_enabled'), 503, 'Billing portal is disabled.');

        $configuration = (string) config('billing.stripe.portal_configuration');
        if (!str_starts_with($configuration, 'bpc_')) {
            throw new RuntimeException('An approved test-mode portal configuration is required.');
        }
        $response = Http::asForm()->withToken($key)->timeout(15)->post(
            'https://api.stripe.com/v1/billing_portal/sessions',
            [
                'customer' => $subscription->gateway_customer_id,
                'return_url' => $returnUrl,
                'configuration' => $configuration,
            ],
        );
        if (!$response->successful()) {
            throw new RuntimeException('Stripe test billing portal is unavailable.');
        }
        $data = $response->json();
        $url = $data['url'] ?? null;
        if (!is_array($data) || ($data['object'] ?? null) !== 'billing_portal.session'
            || ($data['livemode'] ?? true) !== false
            || ($data['customer'] ?? null) !== $subscription->gateway_customer_id
            || !is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https'
            || parse_url($url, PHP_URL_HOST) !== 'billing.stripe.com') {
            throw new RuntimeException('Stripe returned an invalid test billing portal URL.');
        }
        return $url;
    }
}
