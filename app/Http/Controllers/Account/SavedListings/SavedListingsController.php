<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account\SavedListings;

use App\Models\Listing;
use App\Models\SavedListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SavedListingsController
{
    public function index(Request $request): View
    {
        $saved = SavedListing::query()
            ->where('user_id', $request->user()->getKey())
            ->whereHas('listing', fn ($query) => $query->visibleToPublic())
            ->with(['listing.property.company', 'listing.mediaAttachments.asset'])
            ->latest()->paginate(12);

        return view('account.saved.index', ['saved' => $saved]);
    }

    public function store(Request $request, Listing $listing): RedirectResponse
    {
        abort_unless(Listing::query()->visibleToPublic()->whereKey($listing->getKey())->exists(), 404);
        SavedListing::query()->firstOrCreate([
            'user_id' => $request->user()->getKey(),
            'listing_id' => $listing->getKey(),
        ]);

        return back()->with('notice', 'Property saved to your account.');
    }

    public function destroy(Request $request, Listing $listing): RedirectResponse
    {
        SavedListing::query()->where('user_id', $request->user()->getKey())
            ->where('listing_id', $listing->getKey())->delete();
        return back()->with('notice', 'Property removed from your saved list.');
    }
}
