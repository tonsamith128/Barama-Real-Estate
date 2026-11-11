<?php

declare(strict_types=1);

namespace App\Services\Account;

use App\Models\ComparisonItem;
use App\Models\Listing;
use App\Models\RecentListingView;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomerActivityManager
{
    public const MAX_COMPARE = 5;

    public function compare(User $user, Listing $listing): void
    {
        $this->requirePublished($listing);

        DB::transaction(function () use ($user, $listing): void {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            if (ComparisonItem::query()->where('user_id', $user->getKey())
                ->where('listing_id', $listing->getKey())->exists()) {
                return;
            }

            $count = ComparisonItem::query()->where('user_id', $user->getKey())->count();
            if ($count >= self::MAX_COMPARE) {
                throw ValidationException::withMessages([
                    'comparison' => 'You can compare up to five properties.',
                ]);
            }

            ComparisonItem::query()->create([
                'user_id' => $user->getKey(),
                'listing_id' => $listing->getKey(),
                'position' => $count,
            ]);
        });
    }

    public function removeComparison(User $user, Listing $listing): void
    {
        ComparisonItem::query()->where('user_id', $user->getKey())
            ->where('listing_id', $listing->getKey())->delete();
    }

    /**
     * Never track page visits implicitly. The account holder must explicitly
     * choose "Remember this property" on a publicly approved listing.
     */
    public function remember(User $user, Listing $listing): void
    {
        $this->requirePublished($listing);

        DB::transaction(function () use ($user, $listing): void {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $row = RecentListingView::query()->where('user_id', $user->getKey())
                ->where('listing_id', $listing->getKey())->lockForUpdate()->first();

            if ($row === null) {
                RecentListingView::query()->create([
                    'user_id' => $user->getKey(),
                    'listing_id' => $listing->getKey(),
                    'first_viewed_at' => now(),
                    'last_viewed_at' => now(),
                    'view_count' => 1,
                ]);
                return;
            }

            $row->update([
                'last_viewed_at' => now(),
                'view_count' => min(PHP_INT_MAX, $row->view_count + 1),
            ]);
        });
    }

    public function forget(User $user, Listing $listing): void
    {
        RecentListingView::query()->where('user_id', $user->getKey())
            ->where('listing_id', $listing->getKey())->delete();
    }

    public function clearHistory(User $user): void
    {
        RecentListingView::query()->where('user_id', $user->getKey())->delete();
    }

    private function requirePublished(Listing $listing): void
    {
        abort_unless(Listing::query()->visibleToPublic()
            ->whereKey($listing->getKey())->exists(), 404);
    }
}
