<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Billing\PaymentGateway;
use App\Models\BillingPayment;
use App\Models\BillingRefund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class BillingRefundManager
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function request(User $actor, BillingPayment $payment, int $amountCents, string $reason): BillingRefund
    {
        abort_unless($actor->isSystemAdministrator(), 403);
        abort_unless(config('billing.enabled'), 503, 'Billing refunds are disabled.');

        $refund = DB::transaction(function () use ($actor, $payment, $amountCents, $reason): BillingRefund {
            $locked = BillingPayment::query()->whereKey($payment->getKey())
                ->lockForUpdate()->firstOrFail();
            $reserved = BillingRefund::query()->where('billing_payment_id', $locked->getKey())
                ->whereIn('status', ['requested', 'submitted'])->sum('amount_cents');
            if ($amountCents < 1 || $amountCents + $reserved > $locked->amount_cents - $locked->refunded_cents
                || $locked->gateway !== 'stripe_test') {
                throw ValidationException::withMessages([
                    'amount' => 'Refund exceeds the verified, unrefunded and unreserved payment amount.',
                ]);
            }
            $record = BillingRefund::query()->create([
                'reference' => (string) Str::uuid(),
                'billing_payment_id' => $locked->getKey(),
                'requested_by_user_id' => $actor->getKey(),
                'amount_cents' => $amountCents,
                'reason' => $reason,
                'status' => 'requested',
            ]);
            DB::table('audit_events')->insert([
                'actor_id' => $actor->getKey(),
                'action' => 'billing.refund.requested',
                'subject_type' => 'billing_refund',
                'subject_id' => $record->getKey(),
                'context' => json_encode(['amount_cents' => $amountCents], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return $record;
        });

        try {
            $result = $this->gateway->refund($refund);
            $refund->update(['gateway_refund_id' => $result['id'], 'status' => 'submitted']);
            return $refund->fresh();
        } catch (Throwable $e) {
            // A provider timeout may mean the refund succeeded. Never release
            // reserved funds or retry automatically without reconciliation.
            report($e);
            throw new \RuntimeException('Refund status is uncertain. Reconcile at the provider before retrying.', previous: $e);
        }
    }
}
