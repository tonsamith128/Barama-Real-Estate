<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Inquiries;

use App\Models\Inquiry;
use App\Services\Inquiries\InquiryReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AdminInquiryController
{
    public function respond(Request $request, Inquiry $inquiry, InquiryReviewService $service): RedirectResponse
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
        $service->markResponded($request->user(), $inquiry);
        return back()->with('notice', 'Inquiry marked as handled and customer notified when signed in.');
    }

    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
        return view('admin.inquiries.index', [
            'inquiries' => Inquiry::query()->with('listing.property.company')
                ->latest()->paginate(25),
        ]);
    }
}
