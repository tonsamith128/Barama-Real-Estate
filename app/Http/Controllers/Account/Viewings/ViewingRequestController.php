<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account\Viewings;

use App\Models\Listing;
use App\Models\ViewingRequest;
use App\Services\Viewings\ViewingDecisionService;
use App\Services\Viewings\ViewingCalendarExport;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ViewingRequestController
{
    public function index(Request $request, ViewingCalendarExport $calendar): View
    {
        $viewings = ViewingRequest::query()
            ->where('user_id', $request->user()->getKey())
            ->with(['listing.property', 'events'])
            ->latest()->paginate(10);

        // Compute links from the same database-level conditions as downloads.
        // Ineligible historic, withdrawn, held and proposed events show no link.
        $exportableIds = $calendar->eligible()
            ->where('user_id', $request->user()->getKey())
            ->whereKey($viewings->getCollection()->modelKeys())
            ->pluck('viewing_requests.id')->all();

        return view('account.viewings.index', [
            'viewings' => $viewings,
            'calendarExportableIds' => $exportableIds,
        ]);
    }

    public function store(
        Request $request, Listing $listing, ViewingDecisionService $service,
    ): RedirectResponse {
        $data = $request->validate([
            'requested_at' => ['required', 'date', 'after:now', 'before:'.now()->addMonths(6)->toDateString()],
            'message' => ['nullable', 'string', 'max:1500'],
        ]);
        $service->submit($request->user(), $listing,
            CarbonImmutable::parse($data['requested_at']), $data['message'] ?? null);
        return redirect()->route('account.viewings.index')
            ->with('notice', 'Viewing request submitted. This is not a confirmed appointment.');
    }

    public function respond(
        Request $request, ViewingRequest $viewing,
        ViewingDecisionService $service,
    ): RedirectResponse {
        abort_unless((int)$viewing->user_id === (int)$request->user()->getKey(), 404);
        $data = $request->validate(['decision' => ['required', 'in:accept,decline']]);
        $service->respondToProposal($request->user(), $viewing, $data['decision']);

        return back()->with('notice', $data['decision'] === 'accept'
            ? 'You accepted the new viewing time. The appointment is confirmed.'
            : 'You declined the new time and cancelled the viewing request.');
    }

    public function cancel(
        Request $request, ViewingRequest $viewing,
        ViewingDecisionService $service,
    ): RedirectResponse {
        abort_unless((int)$viewing->user_id === (int)$request->user()->getKey(), 404);
        $service->cancel($request->user(), $viewing);
        return back()->with('notice', 'Viewing request cancelled.');
    }
}
