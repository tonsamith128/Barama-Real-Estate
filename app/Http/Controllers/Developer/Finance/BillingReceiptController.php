<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer\Finance;

use App\Models\BillingInvoice;
use App\Models\Company;
use App\Models\CompanyBillingProfile;
use Illuminate\Support\Facades\Schema;
use App\Services\Developer\CompanyTeamManager;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class BillingReceiptController
{
    public function __invoke(
        Request $request,
        Company $company,
        BillingInvoice $invoice,
        CompanyTeamManager $teams,
    ): View {
        $teams->assertOwner($request->user(), $company);
        $invoice->load(['order.company']);
        abort_unless($invoice->order?->company_id === $company->getKey(), 404);
        abort_unless($invoice->paid_at !== null && in_array($invoice->status, [
            'paid', 'partially_refunded', 'refunded',
        ], true), 404);

        return view('developer.finance.receipts.show', [
            'company' => $company,
            'invoice' => $invoice,
            'order' => $invoice->order,
            'billingLocale' => Schema::hasTable('company_billing_profiles')
                ? (CompanyBillingProfile::query()->where('company_id', $company->getKey())
                    ->value('preferred_locale') ?: 'en') : 'en',
        ]);
    }
}
