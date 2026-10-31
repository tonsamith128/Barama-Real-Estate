<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account\Viewings;

use App\Models\Listing;
use App\Models\AccountNotification;
use App\Models\ViewingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ViewingRequestController
{
    public function index(Request $request): View
    {
        return view('account.viewings.index', [
            'viewings' => ViewingRequest::query()->where('user_id', $request->user()->getKey())
                ->with(['listing.property'])->latest()->paginate(15),
        ]);
    }

    public function store(Request $request, Listing $listing): RedirectResponse
    {
        abort_unless(Listing::query()->visibleToPublic()->whereKey($listing->getKey())->exists(), 404);
        $data = $request->validate([
            'requested_at' => ['required', 'date', 'after:now', 'before:'.now()->addMonths(6)->toDateString()],
            'message' => ['nullable', 'string', 'max:1500'],
        ]);
        ViewingRequest::query()->create([
            'user_id' => $request->user()->getKey(),
            'listing_id' => $listing->getKey(),
            'requested_at' => $data['requested_at'],
            'message' => $data['message'] ?? null,
            'status' => 'pending',
        ]);
        AccountNotification::query()->create([
            'user_id' => $request->user()->getKey(),
            'title' => 'Viewing request submitted',
            'message' => 'Your request to view '.$listing->property->title.' was sent. The appointment is not confirmed.',
            'type' => 'viewing',
        ]);
        return redirect()->route('account.viewings.index')
            ->with('notice', 'Viewing request submitted. This is not a confirmed appointment.');
    }

    public function cancel(Request $request, ViewingRequest $viewing): RedirectResponse
    {
        abort_unless($viewing->user_id === $request->user()->getKey(), 404);
        abort_unless($viewing->status === 'pending', 409);
        $viewing->update(['status' => 'cancelled']);
        return back()->with('notice', 'Viewing request cancelled.');
    }
}
