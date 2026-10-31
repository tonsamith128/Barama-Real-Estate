<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Viewings;

use App\Models\ViewingRequest;
use App\Services\Viewings\ViewingDecisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class AdminViewingController
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
        $status = (string) $request->query('status', 'pending');
        abort_unless(in_array($status, ['pending','confirmed','declined','cancelled','all'], true), 422);
        $query = ViewingRequest::query()->with(['listing.property.company'])->latest();
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        return view('admin.viewings.index', [
            'viewings' => $query->paginate(20)->withQueryString(),
            'status' => $status,
        ]);
    }

    public function decide(
        Request $request,
        ViewingRequest $viewing,
        ViewingDecisionService $service,
    ): RedirectResponse {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['confirmed', 'declined'])],
        ]);
        $service->decide($request->user(), $viewing, $data['decision']);
        return back()->with('notice', 'Viewing request '.$data['decision'].' and customer notified.');
    }
}
