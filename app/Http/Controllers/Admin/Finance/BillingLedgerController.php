<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Finance;

use App\Models\BillingOrder;
use App\Models\BillingPayment;
use App\Services\Billing\BillingRefundManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class BillingLedgerController
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
        abort_unless(Schema::hasTable('billing_orders'), 503, 'Run pending billing migrations.');

        return view('admin.finance.billing.index', [
            'orders' => BillingOrder::query()->with(['company', 'invoice', 'payment'])
                ->latest()->paginate(20),
            'payments' => BillingPayment::query()->with(['order.company', 'refunds'])
                ->latest()->limit(25)->get(),
        ]);
    }

    public function refund(
        Request $request,
        BillingPayment $payment,
        BillingRefundManager $manager,
    ): RedirectResponse {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
        $data = $request->validate([
            'amount' => ['required', 'regex:/^(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,2})?$/'],
            'reason' => ['required', 'string', 'min:8', 'max:120'],
        ]);
        [$whole, $fraction] = array_pad(explode('.', (string) $data['amount'], 2), 2, '0');
        $amount = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
        $manager->request($request->user(), $payment, $amount, $data['reason']);

        return back()->with('notice',
            'Test-mode refund submitted. Ledger and entitlement changes require a verified provider webhook.');
    }
}
