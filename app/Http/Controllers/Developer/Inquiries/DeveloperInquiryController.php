<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer\Inquiries;

use App\Models\Inquiry;
use App\Services\Inquiries\InquiryReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DeveloperInquiryController
{
    public function respond(Request $request, Inquiry $inquiry, InquiryReviewService $service): RedirectResponse
    {
        $id = $request->user()->getKey();
        $authorized = Inquiry::query()->whereKey($inquiry->getKey())->whereHas(
            'listing.property.company.memberships',
            function (Builder $member) use ($id): void {
                $member->where('user_id', $id)->where('status', 'active')
                    ->whereIn('role', ['owner', 'manager']);
            }
        )->whereHas('listing.property.company', fn (Builder $company) =>
            $company->where('status', 'approved'))->exists();
        abort_unless($authorized, 404);
        $service->markResponded($request->user(), $inquiry);
        return back()->with('notice', 'Inquiry marked as handled.');
    }

    public function __invoke(Request $request): View
    {
        $id = $request->user()->getKey();
        return view('developer.inquiries.index', [
            'inquiries' => Inquiry::query()->with('listing.property.company')
                ->whereHas('listing.property.company.memberships', function (Builder $memberships) use ($id): void {
                    $memberships->where('user_id', $id)
                        ->where('status', 'active')->whereIn('role', ['owner', 'manager']);
                })->latest()->paginate(25),
        ]);
    }
}
