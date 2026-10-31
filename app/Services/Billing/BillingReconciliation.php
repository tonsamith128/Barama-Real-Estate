<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingDispute;
use App\Models\BillingOrder;
use App\Models\BillingRefund;
use App\Models\BillingTaxReview;
use App\Models\BillingWebhookEvent;
use App\Models\CompanySubscription;
use Illuminate\Support\Facades\Schema;

final class BillingReconciliation
{
    /** Read-only dashboard. Uncertain provider states require manual verification. */
    public function summary(): array
    {
        $required = [
            'billing_orders', 'billing_invoices', 'billing_payments',
            'billing_refunds', 'billing_webhook_events', 'billing_disputes',
            'billing_tax_reviews', 'company_subscriptions',
        ];
        $missing = array_values(array_filter($required, fn (string $table) => !Schema::hasTable($table)));
        if ($missing !== []) {
            return ['ready' => false, 'missing_tables' => $missing];
        }

        return [
            'ready' => true,
            'missing_tables' => [],
            'pending_checkouts' => BillingOrder::query()->where('status', 'pending')
                ->where('created_at', '<', now()->subMinutes(40))->count(),
            'unprocessed_events' => BillingWebhookEvent::query()->where('status', 'received')
                ->where('created_at', '<', now()->subMinutes(5))->count(),
            'uncertain_refunds' => BillingRefund::query()
                ->whereIn('status', ['requested', 'submitted'])
                ->where('created_at', '<', now()->subMinutes(30))->count(),
            'unmatched_disputes' => BillingDispute::query()->where('needs_reconciliation', true)->count(),
            'tax_review_pending' => BillingTaxReview::query()->whereIn('status', ['unreviewed', 'needs_specialist'])->count(),
            'expired_entitlements' => CompanySubscription::query()->where('status', 'active')
                ->whereNotNull('ends_at')->where('ends_at', '<=', now())->count(),
            'checkout_enabled' => (bool) config('billing.enabled'),
            'recurring_test_enabled' => (bool) config('billing.recurring_test_enabled'),
        ];
    }
}
