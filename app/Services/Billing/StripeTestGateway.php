<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Billing\PaymentGateway;
use App\Models\BillingOrder;
use App\Models\BillingRefund;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class StripeTestGateway implements PaymentGateway
{
    private function key(): string
    {
        $key = (string) config('billing.stripe.secret_key');
        if (!str_starts_with($key, 'sk_test_')) {
            throw new RuntimeException('Stripe test-mode credentials are required.');
        }

        return $key;
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('billing.stripe.api_url'), '/').$path;
    }

    public function checkout(BillingOrder $order, string $successUrl, string $cancelUrl): array
    {
        abort_unless(config('billing.enabled'), 503, 'Billing checkout is disabled.');

        $response = Http::asForm()->withToken($this->key())
            ->withHeaders(['Idempotency-Key' => 'barama-checkout-'.$order->reference])
            ->timeout(15)->post($this->endpoint('/v1/checkout/sessions'), [
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'client_reference_id' => $order->reference,
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'expires_at' => now()->addMinutes(30)->timestamp,
                'metadata' => ['order_reference' => $order->reference],
                'payment_intent_data' => ['metadata' => ['order_reference' => $order->reference]],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => $order->amount_cents,
                        'product_data' => ['name' => $order->plan_name],
                    ],
                    'quantity' => 1,
                ]],
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Payment gateway could not create a checkout session.');
        }

        $data = $response->json();
        $url = $data['url'] ?? '';
        $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;
        if (!is_array($data) || !is_string($data['id'] ?? null)
            || !str_starts_with($data['id'], 'cs_test_')
            || !is_string($url) || parse_url($url, PHP_URL_SCHEME) !== 'https'
            || $host !== 'checkout.stripe.com'
            || ($data['livemode'] ?? true) !== false
            || ($data['mode'] ?? null) !== 'payment'
            || ($data['currency'] ?? null) !== 'usd'
            || (int) ($data['amount_total'] ?? -1) !== $order->amount_cents) {
            throw new RuntimeException('Gateway checkout response failed validation.');
        }

        return ['id' => $data['id'], 'url' => $url];
    }

    public function refund(BillingRefund $refund): array
    {
        abort_unless(config('billing.enabled'), 503, 'Billing refund requests are disabled.');
        $payment = $refund->payment;
        if ($payment === null || !str_starts_with($payment->gateway_payment_id, 'pi_')) {
            throw new RuntimeException('No verified Stripe payment to refund.');
        }

        $response = Http::asForm()->withToken($this->key())
            ->withHeaders(['Idempotency-Key' => 'barama-refund-'.$refund->reference])
            ->timeout(15)->post($this->endpoint('/v1/refunds'), [
                'payment_intent' => $payment->gateway_payment_id,
                'amount' => $refund->amount_cents,
                'reason' => 'requested_by_customer',
                'metadata' => ['refund_reference' => $refund->reference],
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Gateway rejected or could not accept the refund request.');
        }

        $data = $response->json();
        if (!is_array($data) || !is_string($data['id'] ?? null)
            || !str_starts_with($data['id'], 're_')
            || ($data['livemode'] ?? true) !== false
            || ($data['payment_intent'] ?? null) !== $payment->gateway_payment_id
            || ($data['currency'] ?? null) !== strtolower($payment->currency)
            || (int) ($data['amount'] ?? -1) !== $refund->amount_cents) {
            throw new RuntimeException('Gateway refund response failed validation.');
        }

        return ['id' => $data['id']];
    }
}
