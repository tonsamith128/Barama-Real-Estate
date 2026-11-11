<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\CustomerRecovery;

use App\Models\LegacyCustomerInquiry;
use App\Models\LegacyCustomerLead;
use App\Services\Moderation\ArchivedCustomerReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ArchivedCustomerReviewController
{
    private function authorize(Request $request): void
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
    }

    public function index(Request $request): View
    {
        $this->authorize($request);
        abort_unless(Schema::hasTable('legacy_customer_inquiries')
            && Schema::hasTable('legacy_customer_leads'), 409);
        $status = (string)$request->query('status','needs_review');
        abort_unless(in_array($status,
            ['needs_review','needs_followup','reviewed','all'], true), 422);
        $inquiries = LegacyCustomerInquiry::query()->with(['company','reviewer'])->latest();
        $leads = LegacyCustomerLead::query()->with(['company','reviewer'])->latest();
        if ($status !== 'all') {
            $inquiries->where('review_status',$status);
            $leads->where('review_status',$status);
        }
        return view('admin.customer-recovery.index', [
            'inquiries'=>$inquiries->paginate(10,['*'],'inquiries_page')->withQueryString(),
            'leads'=>$leads->paginate(10,['*'],'leads_page')->withQueryString(),
            'status'=>$status,
        ]);
    }

    public function inquiry(Request $request, LegacyCustomerInquiry $inquiry,
        ArchivedCustomerReviewService $service): RedirectResponse
    {
        $this->authorize($request);
        $data = $this->validated($request);
        $service->reviewInquiry($request->user(),$inquiry,$data['decision'],$data['note']);
        return back()->with('notice','Historical inquiry review recorded. No customer email was sent.');
    }

    public function lead(Request $request, LegacyCustomerLead $lead,
        ArchivedCustomerReviewService $service): RedirectResponse
    {
        $this->authorize($request);
        $data = $this->validated($request);
        $service->reviewLead($request->user(),$lead,$data['decision'],$data['note']);
        return back()->with('notice','Historical lead review recorded. No sales contact was activated.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'decision'=>['required',Rule::in(['reviewed','needs_followup'])],
            'note'=>['required','string','min:3','max:1000'],
        ]);
    }
}
