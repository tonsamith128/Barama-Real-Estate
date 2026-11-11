<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer\Viewings;

use App\Models\ViewingRequest;
use App\Services\Viewings\ViewingDecisionService;
use Carbon\CarbonImmutable;
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

    private function authorize(Request $request, ViewingRequest $viewing): void
    {
        abort_unless($this->accessible($request)->whereKey($viewing->getKey())->exists(), 404);
    }

    public function index(Request $request): View
    {
        $status = (string)$request->query('status', 'active');
        abort_unless(in_array($status,
            ['active','pending','confirmed','reschedule_proposed','legacy_review','cancelled','all'],
            true), 422);
        $query = $this->accessible($request)
            ->with(['listing.property.company', 'user', 'events'])
            ->latest();
        if ($status === 'active') {
            $query->whereIn('status', ['pending','reschedule_proposed','confirmed']);
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }
        return view('developer.viewings.index', [
            'viewings' => $query->paginate(10)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function decide(Request $request, ViewingRequest $viewing,
        ViewingDecisionService $service): RedirectResponse
    {
        $this->authorize($request, $viewing);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['confirmed', 'declined'])],
        ]);
        $service->decide($request->user(), $viewing, $data['decision']);
        return back()->with('notice', 'Viewing request '.$data['decision'].' and customer notified.');
    }

    public function propose(Request $request, ViewingRequest $viewing,
        ViewingDecisionService $service): RedirectResponse
    {
        $this->authorize($request, $viewing);
        $data = $request->validate([
            'proposed_at' => ['required','date','after:now'],
            'reason' => ['nullable','string','max:1000'],
        ]);
        $service->propose($request->user(), $viewing,
            CarbonImmutable::parse($data['proposed_at']), $data['reason'] ?? null);
        return back()->with('notice', 'New time proposed. Customer approval is required.');
    }

    public function cancel(Request $request, ViewingRequest $viewing,
        ViewingDecisionService $service): RedirectResponse
    {
        $this->authorize($request, $viewing);
        $service->cancel($request->user(), $viewing, true);
        return back()->with('notice', 'Viewing cancelled. Customer notified.');
    }
}
