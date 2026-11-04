<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account\Dashboard;

use App\Models\AccountNotification;
use App\Models\Inquiry;
use App\Models\SavedListing;
use App\Models\SavedSearch;
use App\Models\ViewingRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

final class DashboardController
{
    public function __invoke(Request $request): View
    {
        $id = $request->user()->getKey();
        $hasSavedSearches = Schema::hasTable('saved_searches');
        $savedSearchCount = $hasSavedSearches
            ? SavedSearch::query()->where('user_id', $id)->count() : 0;
        $recentSearches = $hasSavedSearches
            ? SavedSearch::query()->where('user_id', $id)->latest()->limit(3)->get()
            : collect();

        $saved = SavedListing::query()->where('user_id', $id)
            ->whereHas('listing', fn ($query) => $query->visibleToPublic());
        $unread = AccountNotification::query()->where('user_id', $id)->whereNull('read_at');

        return view('account.dashboard.index', [
            'metrics' => [
                'Saved homes' => (clone $saved)->count(),
                'Saved searches' => $savedSearchCount,
                'Inquiries sent' => Inquiry::query()->where('requester_id', $id)->count(),
                'Viewing requests' => ViewingRequest::query()->where('user_id', $id)->count(),
                'Unread notifications' => (clone $unread)->count(),
            ],
            'recentSearches' => $recentSearches,
            'recentSaved' => $saved->with(['listing.property.company'])
                ->latest()->limit(4)->get(),
            'recentInquiries' => Inquiry::query()->where('requester_id', $id)
                ->with('listing.property')->latest()->limit(4)->get(),
            'upcomingViewings' => ViewingRequest::query()->where('user_id', $id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->where('requested_at', '>=', now())
                ->with('listing.property')
                ->orderBy('requested_at')->limit(3)->get(),
            'unreadNotifications' => $unread->latest()->limit(3)->get(),
        ]);
    }
}
