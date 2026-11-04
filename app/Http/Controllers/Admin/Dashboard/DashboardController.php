<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Dashboard;

use App\Models\Company;
use App\Models\ContactMessage;
use App\Models\Inquiry;
use App\Models\LegalPolicy;
use App\Models\Listing;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\Property;
use App\Models\User;
use App\Models\ViewingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class DashboardController
{
    /**
     * Avoid optional-table SQL failures after a code-only upgrade.
     * Never hide unrelated database failures or silently run migrations.
     */
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);

        $required = [
            'companies', 'projects', 'properties', 'listings', 'media_assets',
            'inquiries', 'legal_policies', 'contact_messages',
            'viewing_requests',
        ];
        $exists = [];
        foreach ($required as $table) {
            $exists[$table] = Schema::hasTable($table);
        }
        $count = static fn (string $table, callable $query): int =>
            $exists[$table] ? (int) $query() : 0;

        // A fixed allowlist prevents unbounded reports on large installations.
        $days = in_array($request->query('days'), ['7', '30'], true)
            ? (int) $request->query('days') : 7;
        $since = now()->startOfDay()->subDays($days - 1);
        $daily = [];
        foreach (['inquiries', 'viewing_requests'] as $table) {
            $daily[$table] = $exists[$table]
                ? DB::table($table)->selectRaw('DATE(created_at) AS activity_day, COUNT(*) AS total')
                    ->where('created_at', '>=', $since)
                    ->groupByRaw('DATE(created_at)')
                    ->pluck('total', 'activity_day')->all()
                : [];
        }
        $activity = [];
        for ($day = 0; $day < $days; $day++) {
            $date = $since->copy()->addDays($day);
            $key = $date->toDateString();
            $activity[] = [
                'label' => $date->format('M j'),
                'inquiries' => (int) ($daily['inquiries'][$key] ?? 0),
                'viewings' => (int) ($daily['viewing_requests'][$key] ?? 0),
            ];
        }
        $activityMax = max(1, ...array_map(
            static fn (array $point): int => $point['inquiries'] + $point['viewings'],
            $activity,
        ));

        $reviewCounts = [
            'projects' => $count('projects', fn () => Project::query()->where('status', 'pending')->count()),
            'listings' => $count('listings', fn () => Listing::query()->where('status', 'pending')->count()),
            'inquiries' => $count('inquiries', fn () => Inquiry::query()->where('status', 'new')->count()),
            'viewings' => $count('viewing_requests', fn () => ViewingRequest::query()->where('status', 'pending')->count()),
            'contacts' => $count('contact_messages', fn () => ContactMessage::query()->where('status', 'new')->count()),
        ];

        return view('admin.dashboard.index', [
            'counts' => [
                'Users' => User::query()->count(),
                'Companies' => $count('companies', fn () => Company::query()->count()),
                'Projects' => $count('projects', fn () => Project::query()->count()),
                'Properties' => $count('properties', fn () => Property::query()->count()),
                'Listings' => $count('listings', fn () => Listing::query()->count()),
                'Media images' => $count('media_assets', fn () => MediaAsset::query()->count()),
                'New inquiries' => $reviewCounts['inquiries'],
                'Contact messages' => $reviewCounts['contacts'],
                'Viewing requests' => $reviewCounts['viewings'],
                'Draft legal policies' => $count('legal_policies', fn () => LegalPolicy::query()->where('status', 'draft')->count()),
            ],
            'reviewCounts' => $reviewCounts,
            'pendingProjects' => $exists['projects']
                ? Project::query()->where('status', 'pending')->with('company')->oldest()->limit(4)->get()
                : collect(),
            'pendingListings' => $exists['listings']
                ? Listing::query()->where('status', 'pending')->with('property.company')->oldest()->limit(4)->get()
                : collect(),
            'pendingViewings' => $exists['viewing_requests']
                ? ViewingRequest::query()->where('status', 'pending')->with(['listing.property', 'user'])
                    ->orderBy('requested_at')->limit(4)->get() : collect(),
            'activityDays' => $days,
            'activity' => $activity,
            'activityMax' => $activityMax,
            'missingTables' => array_keys(array_filter($exists, fn (bool $present) => !$present)),
            'publishedListingCount' => $exists['listings']
                ? Listing::query()->visibleToPublic()->count() : 0,
            'recentInquiries' => $exists['inquiries']
                ? Inquiry::query()->with('listing.property')->latest()->limit(5)->get() : collect(),
            'recentContacts' => $exists['contact_messages']
                ? ContactMessage::query()->latest()->limit(5)->get() : collect(),
        ]);
    }
}
