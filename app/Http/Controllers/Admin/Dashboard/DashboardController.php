<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Dashboard;

use App\Models\Company;
use App\Models\ContactMessage;
use App\Models\DemoImport;
use App\Models\Inquiry;
use App\Models\LegalPolicy;
use App\Models\Listing;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\Property;
use App\Models\User;
use App\Models\ViewingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class DashboardController
{
    /**
     * Older installations may receive new controller files before migrations
     * run. Surface actionable schema status instead of an SQL 500.
     * Other database errors continue to reach Laravel exception reporting.
     */
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);

        $required = [
            'companies', 'projects', 'properties', 'listings', 'media_assets',
            'inquiries', 'legal_policies', 'demo_imports', 'contact_messages',
            'viewing_requests',
        ];
        $exists = [];
        foreach ($required as $table) {
            $exists[$table] = Schema::hasTable($table);
        }
        $count = static fn (string $table, callable $query): int =>
            $exists[$table] ? (int) $query() : 0;

        return view('admin.dashboard.index', [
            'counts' => [
                'Users' => User::query()->count(),
                'Companies' => $count('companies', fn () => Company::query()->count()),
                'Projects' => $count('projects', fn () => Project::query()->count()),
                'Properties' => $count('properties', fn () => Property::query()->count()),
                'Listings' => $count('listings', fn () => Listing::query()->count()),
                'Media images' => $count('media_assets', fn () => MediaAsset::query()->count()),
                'New inquiries' => $count('inquiries', fn () => Inquiry::query()->where('status', 'new')->count()),
                'Contact messages' => $count('contact_messages', fn () => ContactMessage::query()->where('status', 'new')->count()),
                'Viewing requests' => $count('viewing_requests', fn () => ViewingRequest::query()->where('status', 'pending')->count()),
                'Draft legal policies' => $count('legal_policies', fn () => LegalPolicy::query()->where('status', 'draft')->count()),
            ],
            'missingTables' => array_keys(array_filter($exists, fn (bool $present) => !$present)),
            'import' => $exists['demo_imports'] ? DemoImport::query()->latest()->first() : null,
            'recentInquiries' => $exists['inquiries']
                ? Inquiry::query()->with('listing.property')->latest()->limit(5)->get() : collect(),
            'recentContacts' => $exists['contact_messages']
                ? ContactMessage::query()->latest()->limit(5)->get() : collect(),
        ]);
    }
}
