<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account\Inquiries;

use App\Models\Inquiry;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class AccountInquiryController
{
    public function __invoke(Request $request): View
    {
        return view('account.inquiries.index', [
            'inquiries' => Inquiry::query()
                ->where('requester_id', $request->user()->getKey())
                ->with(['listing.property'])
                ->latest()->paginate(15),
        ]);
    }
}
