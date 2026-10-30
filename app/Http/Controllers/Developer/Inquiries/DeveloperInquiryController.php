<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer\Inquiries;

use App\Models\Inquiry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DeveloperInquiryController
{
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
