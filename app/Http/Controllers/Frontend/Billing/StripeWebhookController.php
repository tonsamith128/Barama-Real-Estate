<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Billing;

use App\Services\Billing\BillingWebhookProcessor;
use App\Services\Billing\StripeSignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class StripeWebhookController
{
    public function __invoke(
        Request $request,
        StripeSignatureVerifier $signature,
        BillingWebhookProcessor $processor,
    ): JsonResponse {
        if (!config('billing.enabled')) {
            return response()->json(['error' => 'Billing is disabled.'], 503);
        }
        try {
            $event = $signature->verify($request->getContent(), (string) $request->header('Stripe-Signature', ''));
        } catch (ValidationException) {
            return response()->json(['error' => 'Invalid webhook signature or payload.'], 400);
        }

        try {
            $processor->process($event);
        } catch (ValidationException) {
            // Reject unrecognized/mismatched provider objects. Stripe may retry
            // when a legitimate event arrived before session reconciliation.
            return response()->json(['error' => 'Webhook not reconciled.'], 409);
        }

        return response()->json(['received' => true]);
    }
}
