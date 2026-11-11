<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Viewings;

use App\Models\ViewingRequest;
use App\Services\Viewings\ViewingDecisionService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AdminViewingController
{
    private function authorize(Request $request): void
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
    }

    public function index(Request $request): View
    {
        $this->authorize($request);
        $status = (string)$request->query('status', 'pending');
        abort_unless(in_array($status, [
            'pending','confirmed','declined','cancelled',
            'reschedule_proposed','legacy_review','all',
        ], true), 422);
        $review = (string)$request->query('review', 'all');
        abort_unless(in_array($review, ['clear','held','all'], true), 422);
        $query = ViewingRequest::query()
            ->with(['listing.property.company', 'user', 'events.actor'])->latest();
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($review !== 'all') {
            $query->where('review_status', $review);
        }
        return view('admin.viewings.index', [
            'viewings' => $query->paginate(10)->withQueryString(),
            'status' => $status,
            'review' => $review,
        ]);
    }

    public function decide(Request $request, ViewingRequest $viewing,
        ViewingDecisionService $service): RedirectResponse
    {
        $this->authorize($request);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['confirmed', 'declined'])],
        ]);
        $service->decide($request->user(), $viewing, $data['decision']);
        return back()->with('notice', 'Viewing request '.$data['decision'].' and customer notified.');
    }

    public function propose(Request $request, ViewingRequest $viewing,
        ViewingDecisionService $service): RedirectResponse
    {
        $this->authorize($request);
        $data = $request->validate([
            'proposed_at' => ['required','date','after:now'],
            'reason' => ['nullable','string','max:1000'],
        ]);
        $service->propose($request->user(), $viewing,
            CarbonImmutable::parse($data['proposed_at']), $data['reason'] ?? null);
        return back()->with('notice', 'Proposed time sent to customer for acceptance.');
    }

    public function cancel(Request $request, ViewingRequest $viewing,
        ViewingDecisionService $service): RedirectResponse
    {
        $this->authorize($request);
        $service->cancel($request->user(), $viewing, true);
        return back()->with('notice', 'The request was cancelled and the customer notified.');
    }

    public function review(Request $request, ViewingRequest $viewing,
        ViewingDecisionService $service): RedirectResponse
    {
        $this->authorize($request);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['hold','release'])],
            'reason' => ['required','string','min:3','max:1000'],
        ]);
        $service->review($request->user(), $viewing,
            $data['decision'], $data['reason']);
        return back()->with('notice', 'Administrative review status updated and recorded.');
    }
}
