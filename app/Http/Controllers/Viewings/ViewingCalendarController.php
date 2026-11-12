<?php

declare(strict_types=1);

namespace App\Http\Controllers\Viewings;

use App\Models\CompanyMembership;
use App\Models\ViewingRequest;
use App\Services\Viewings\ViewingCalendarExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ViewingCalendarController
{
    public function customer(
        Request $request, ViewingRequest $viewing, ViewingCalendarExport $calendar,
    ): Response {
        // No IDOR: the same user must own the request, and current state is
        // re-read from the database at download time.
        abort_unless((int) $viewing->user_id === (int) $request->user()->getKey(), 404);

        $eligible = $calendar->eligible()->whereKey($viewing->getKey())
            ->with('listing.property')->firstOrFail();

        return $this->download(
            $calendar->render([$eligible]),
            'barama-viewing-'.$eligible->getKey().'.ics',
        );
    }

    public function company(Request $request, ViewingCalendarExport $calendar): Response
    {
        $userId = $request->user()->getKey();

        $managesApprovedCompany = CompanyMembership::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->whereIn('role', ['owner', 'manager'])
            ->whereHas('company', fn (Builder $query) => $query->where('status', 'approved'))
            ->exists();

        abort_unless($managesApprovedCompany, 403);

        // Authorization is evaluated per viewing; a revoked membership or
        // another company's appointments must never leak into the export.
        $viewings = $calendar->eligible()
            ->whereHas('listing.property.company.memberships', function (Builder $query) use ($userId): void {
                $query->where('user_id', $userId)
                    ->where('status', 'active')
                    ->whereIn('role', ['owner', 'manager']);
            })
            ->with('listing.property')
            ->orderBy('requested_at')
            ->orderBy('viewing_requests.id')
            ->limit(250)
            ->get();

        return $this->download($calendar->render($viewings), 'barama-company-viewings.ics');
    }

    private function download(string $content, string $filename): Response
    {
        return response($content, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
