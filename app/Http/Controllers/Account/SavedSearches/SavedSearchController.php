<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account\SavedSearches;

use App\Http\Requests\Account\SavedSearchRequest;
use App\Models\SavedSearch;
use App\Services\Account\SavedSearchManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class SavedSearchController
{
    public function index(Request $request): View
    {
        $this->requireMigrated();
        $savedSearches = SavedSearch::query()->where('user_id', $request->user()->getKey())
            ->latest()->paginate(12);
        return view('account.searches.index', ['savedSearches' => $savedSearches]);
    }

    public function store(SavedSearchRequest $request, SavedSearchManager $manager): RedirectResponse
    {
        $this->requireMigrated();
        $data = $request->validated();
        $manager->create($request->user(), $data['name'], $request->filters());
        return redirect()->route('account.searches.index')
            ->with('notice', 'Your search is saved. Open it anytime to apply the same property filters.');
    }

    public function rename(
        Request $request, SavedSearch $savedSearch, SavedSearchManager $manager,
    ): RedirectResponse {
        $this->requireMigrated();
        $data = $request->validate(['name' => ['required', 'string', 'min:3', 'max:70']]);
        $manager->rename($request->user(), $savedSearch, $data['name']);
        return redirect()->route('account.searches.index')->with('notice', 'Search renamed.');
    }

    public function destroy(
        Request $request, SavedSearch $savedSearch, SavedSearchManager $manager,
    ): RedirectResponse {
        $this->requireMigrated();
        $manager->remove($request->user(), $savedSearch);
        return redirect()->route('account.searches.index')->with('notice', 'Saved search removed.');
    }

    private function requireMigrated(): void
    {
        abort_unless(Schema::hasTable('saved_searches'), 409, 'Saved searches require the latest database migration.');
    }
}
