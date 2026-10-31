<?php

declare(strict_types=1);

namespace App\Services\Billing;

use Illuminate\Validation\ValidationException;

final class StripeSignatureVerifier
{
    public function verify(string $payload, string $header): array
    {
        $secret = (string) config('billing.stripe.webhook_secret');
        if (!config('billing.enabled') || !str_starts_with($secret, 'whsec_')
            || strlen($payload) < 2 || strlen($payload) > 262144
            || strlen($header) > 2000) {
            throw ValidationException::withMessages(['signature' => 'Webhook cannot be verified.']);
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key === 't' && $value !== null && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== null && preg_match('/^[a-f0-9]{64}$/i', $value)) {
                $signatures[] = strtolower($value);
            }
        }
        if ($timestamp === null || $timestamp < 1 || abs(time() - $timestamp) > 300 || $signatures === []) {
            throw ValidationException::withMessages(['signature' => 'Webhook timestamp or signature is invalid.']);
        }

        $digest = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        $matched = false;
        foreach ($signatures as $signature) {
            $matched = hash_equals($digest, $signature) || $matched;
        }
        if (!$matched) {
            throw ValidationException::withMessages(['signature' => 'Webhook signature mismatch.']);
        }

        try {
            $event = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['payload' => 'Webhook payload is not valid JSON.']);
        }
        if (!is_array($event) || !is_string($event['id'] ?? null)
            || !preg_match('/^evt_[A-Za-z0-9_]{4,180}$/', $event['id'])
            || !is_string($event['type'] ?? null)
            || !isset($event['data']['object']) || !is_array($event['data']['object'])
            || ($event['livemode'] ?? true) !== false) {
            throw ValidationException::withMessages(['payload' => 'Webhook event is not an expected Stripe test event.']);
        }

        return $event;
    }
}
