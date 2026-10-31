<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\Models\BillingTaxReview;
use App\Services\Billing\GlobalBillingPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class BillingTaxReviewController
{
    public function update(Request $request, BillingTaxReview $review): RedirectResponse
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['unreviewed', 'needs_specialist', 'reviewed_nonstatutory'])],
            'jurisdiction' => ['nullable', Rule::in(GlobalBillingPreferences::COUNTRIES)],
            'review_note' => ['required', 'string', 'min:20', 'max:1500'],
        ]);

        DB::transaction(function () use ($request, $review, $data): void {
            $locked = BillingTaxReview::query()->lockForUpdate()->findOrFail($review->getKey());
            $invoice = $locked->invoice;
            abort_unless($invoice !== null && $invoice->paid_at !== null, 409,
                'A tax review requires a verified paid payment record.');

            $locked->update($data);
            // Tax status is a work-queue assessment only. It does not change
            // taxable totals, issue tax invoices or approve merchant eligibility.
            DB::table('audit_events')->insert([
                'actor_id' => $request->user()->getKey(),
                'action' => 'billing.tax_review.recorded',
                'subject_type' => 'billing_tax_review',
                'subject_id' => $locked->getKey(),
                'context' => json_encode([
                    'status' => $data['status'], 'jurisdiction' => $data['jurisdiction'] ?? null,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return redirect()->route('admin.finance.reconciliation.index')
            ->with('notice', 'Non-statutory tax review noted. No tax invoice was issued.');
    }
}
