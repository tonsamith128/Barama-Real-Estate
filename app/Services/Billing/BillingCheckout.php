<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Billing\PaymentGateway;
use App\Models\BillingInvoice;
use App\Models\BillingOrder;
use App\Models\CommercialPlan;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class BillingCheckout
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /** @return array{id: string, url: string} */
    public function start(User $actor, Company $company, CommercialPlan $plan): array
    {
        abort_unless(config('billing.enabled'), 503, 'Billing checkout is disabled.');
        abort_unless($plan->is_active && $plan->price_cents > 0
            && $plan->currency === 'USD', 422);

        $order = DB::transaction(function () use ($actor, $company, $plan): BillingOrder {
            Company::query()->whereKey($company->getKey())->lockForUpdate()->firstOrFail();
            $pending = BillingOrder::query()->where('company_id', $company->getKey())
                ->where('status', 'pending')
                ->where('created_at', '>', now()->subMinutes(35))->exists();
            if ($pending) {
                throw ValidationException::withMessages([
                    'checkout' => 'A checkout is already pending for this company. Finish or cancel it first.',
                ]);
            }

            $order = BillingOrder::query()->create([
                'company_id' => $company->getKey(),
                'commercial_plan_id' => $plan->getKey(),
                'requested_by_user_id' => $actor->getKey(),
                'reference' => (string) Str::uuid(),
                'gateway' => 'stripe_test',
                'status' => 'pending',
                'plan_name' => $plan->name,
                'amount_cents' => $plan->price_cents,
                'currency' => 'USD',
                'billing_period' => $plan->billing_period,
                'listing_limit' => $plan->listing_limit,
            ]);
            BillingInvoice::query()->create([
                'billing_order_id' => $order->getKey(),
                'invoice_number' => 'BRM-'.str_pad((string) $order->getKey(), 8, '0', STR_PAD_LEFT),
                'total_cents' => $order->amount_cents, 'currency' => $order->currency,
                'status' => 'open',
            ]);
            return $order;
        });

        try {
            $urls = $this->gateway->checkout(
                $order,
                route('developer.finance.plans.index', $company).'?checkout=returned',
                route('developer.finance.plans.index', $company).'?checkout=cancelled',
            );

            $order->update(['gateway_session_id' => $urls['id']]);
            return $urls;
        } catch (Throwable $e) {
            // A timeout can be ambiguous. Preserve the ledger and block automatic
            // retries until the checkout session is known to be expired.
            report($e);
            throw new \RuntimeException('Checkout could not be confirmed; no activation occurred.', previous: $e);
        }
    }
}
