<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account\Activity;

use App\Models\ComparisonItem;
use App\Models\Listing;
use App\Models\RecentListingView;
use App\Services\Account\CustomerActivityManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CustomerActivityController
{
    public function index(Request $request): View
    {
        $userId = $request->user()->getKey();

        return view('account.activity.index', [
            'comparison' => ComparisonItem::query()
                ->where('user_id', $userId)
                ->whereHas('listing', fn ($query) => $query->visibleToPublic())
                ->with('listing.property.company')
                ->orderBy('position')->orderBy('id')->get(),
            'recent' => RecentListingView::query()
                ->where('user_id', $userId)
                ->whereHas('listing', fn ($query) => $query->visibleToPublic())
                ->with('listing.property.company')
                ->orderByDesc('last_viewed_at')->paginate(10),
        ]);
    }

    public function compare(
        Request $request, Listing $listing, CustomerActivityManager $manager,
    ): RedirectResponse {
        $manager->compare($request->user(), $listing);

        return redirect()->route('account.activity.index')
            ->with('notice', 'Property added to your private comparison list.');
    }

    public function removeComparison(
        Request $request, Listing $listing, CustomerActivityManager $manager,
    ): RedirectResponse {
        $manager->removeComparison($request->user(), $listing);

        return back()->with('notice', 'Property removed from your comparison list.');
    }

    public function remember(
        Request $request, Listing $listing, CustomerActivityManager $manager,
    ): RedirectResponse {
        $manager->remember($request->user(), $listing);

        return back()->with('notice', 'Property saved to your private recent history.');
    }

    public function forget(
        Request $request, Listing $listing, CustomerActivityManager $manager,
    ): RedirectResponse {
        $manager->forget($request->user(), $listing);

        return back()->with('notice', 'Property removed from your recent history.');
    }

    public function clear(Request $request, CustomerActivityManager $manager): RedirectResponse
    {
        $manager->clearHistory($request->user());

        return back()->with('notice', 'Your recent property history has been cleared.');
    }
}
