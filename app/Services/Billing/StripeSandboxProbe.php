<?php

declare(strict_types=1);

namespace App\Services\Billing;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Explicit read-only Stripe TEST account connectivity probe.
 * A successful /v1/account response does not prove merchant eligibility,
 * invoice API compatibility, webhook delivery, charge collection or payouts.
 */
final class StripeSandboxProbe
{
    public function check(): array
    {
        $fail = static fn (string $reason): array => [
            'status' => $reason,
            'test_api_connection' => false,
            'merchant_eligible' => false,
            'live_payments_allowed' => false,
            'signed_webhooks_verified' => false,
        ];

        if (!config('billing.stripe.sandbox_probe_enabled')) {
            return $fail('disabled');
        }
        $key = (string) config('billing.stripe.secret_key');
        if (!str_starts_with($key, 'sk_test_') || strlen($key) < 12) {
            return $fail('test_credential_required');
        }
        try {
            $response = Http::withToken($key)->acceptJson()
                ->withOptions(['allow_redirects' => false])
                ->timeout(10)->get('https://api.stripe.com/v1/account');
            if (!$response->successful()) {
                return $fail('test_api_unavailable');
            }
            $data = $response->json();
            if (!is_array($data) || ($data['object'] ?? null) !== 'account'
                || !is_string($data['id'] ?? null)
                || !str_starts_with($data['id'], 'acct_')
                || !is_string($data['country'] ?? null)
                || !preg_match('/^[A-Z]{2}$/D', $data['country'])) {
                return $fail('invalid_provider_response');
            }
            // Never output key, full Stripe account ID or customer data.
            return [
                'status' => 'test_api_account_reachable',
                'test_api_connection' => true,
                'account_country' => $data['country'],
                'merchant_eligible' => false,
                'live_payments_allowed' => false,
                'signed_webhooks_verified' => false,
            ];
        } catch (Throwable) {
            return $fail('network_or_provider_error');
        }
    }
}
