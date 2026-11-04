<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer\Dashboard;

use App\Models\CompanyMembership;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\Project;
use App\Models\Property;
use App\Models\ViewingRequest;
use App\Services\Developer\AccessibleCompanies;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class DeveloperDashboardController
{
    public function __invoke(Request $request, AccessibleCompanies $access): View
    {
        $userId = $request->user()->getKey();
        $companies = $access->forUser($request->user());

        // Counts are eagerly loaded for the visible page, not queried per card.
        $companies->getCollection()->loadCount([
            'properties',
            'projects',
            'memberships as active_members_count' => fn (Builder $query) => $query->where('status', 'active'),
        ]);
        $roles = CompanyMembership::query()
            ->where('user_id', $userId)->where('status', 'active')
            ->whereIn('company_id', $companies->getCollection()->modelKeys())
            ->pluck('role', 'company_id');

        $memberOf = fn (Builder $query) => $query
            ->where('user_id', $userId)->where('status', 'active');
        $managerOf = fn (Builder $query) => $query
            ->where('user_id', $userId)->where('status', 'active')
            ->whereIn('role', ['owner', 'manager']);

        // Lead access matches the protected Developer Inquiries/Viewings pages.
        $managedCompany = fn (Builder $query) => $query
            ->where('status', 'approved')
            ->whereHas('memberships', $managerOf);
        $inquiries = Inquiry::query()->whereHas('listing.property.company', $managedCompany);
        $viewings = ViewingRequest::query()->whereHas('listing.property.company', $managedCompany);

        return view('developer.dashboard.index', [
            'companies' => $companies,
            'roles' => $roles,
            'metrics' => [
                'Active companies' => $companies->total(),
                'Company properties' => Property::query()->whereHas('company.memberships', $memberOf)->count(),
                'Projects' => Project::query()->whereHas('company.memberships', $memberOf)->count(),
                'Listings awaiting review' => Listing::query()->where('status', 'pending')
                    ->whereHas('property.company.memberships', $memberOf)->count(),
            ],
            'newInquiries' => (clone $inquiries)->where('status', 'new')->count(),
            'pendingViewings' => (clone $viewings)->where('status', 'pending')->count(),
            'recentInquiries' => $inquiries->with(['listing.property.company'])
                ->latest()->limit(5)->get(),
        ]);
    }
}
