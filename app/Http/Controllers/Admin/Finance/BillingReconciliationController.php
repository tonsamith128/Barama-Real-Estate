<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\Models\BillingDispute;
use App\Models\BillingTaxReview;
use App\Services\Billing\BillingReconciliation;
use App\Services\Billing\BillingReleaseReadiness;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BillingReconciliationController
{
    public function __invoke(Request $request, BillingReconciliation $reconciliation, BillingReleaseReadiness $readiness): View
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);

        $summary = $reconciliation->summary();

        return view('admin.finance.reconciliation.index', [
            'summary' => $summary,
            'readiness' => $readiness->report(),
            'disputes' => $summary['ready']
                ? BillingDispute::query()->with('payment.order.company')->latest()->limit(30)->get()
                : collect(),
            'taxReviews' => $summary['ready']
                ? BillingTaxReview::query()->with('invoice.order.company')->latest()->limit(30)->get()
                : collect(),
        ]);
    }
}
