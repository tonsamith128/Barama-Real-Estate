<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer\Viewings;

use App\Models\ViewingRequest;
use App\Services\Viewings\ViewingDecisionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class DeveloperViewingController
{
    private function accessible(Request $request): Builder
    {
        $id = $request->user()->getKey();
        return ViewingRequest::query()->whereHas(
            'listing.property.company.memberships',
            function (Builder $query) use ($id): void {
                $query->where('user_id', $id)->where('status', 'active')
                    ->whereIn('role', ['owner', 'manager']);
            }
        )->whereHas('listing.property.company', fn (Builder $query) =>
            $query->where('status', 'approved'));
    }

    public function index(Request $request): View
    {
        return view('developer.viewings.index', [
            'viewings' => $this->accessible($request)
                ->with(['listing.property.company', 'user'])->latest()->paginate(20),
        ]);
    }

    public function decide(
        Request $request,
        ViewingRequest $viewing,
        ViewingDecisionService $service,
    ): RedirectResponse {
        // A leaked record ID must not reveal its existence to other companies.
        abort_unless($this->accessible($request)->whereKey($viewing->getKey())->exists(), 404);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['confirmed', 'declined'])],
        ]);
        $service->decide($request->user(), $viewing, $data['decision']);
        return back()->with('notice', 'Viewing request '.$data['decision'].' and customer notified.');
    }
}
