<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Dashboard;

use App\Models\Company;
use App\Models\ContactMessage;
use App\Models\Inquiry;
use App\Models\User;
use App\Models\ViewingRequest;
use App\Models\DemoImport;
use App\Models\LegalPolicy;
use App\Models\Listing;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);

        return view('admin.dashboard.index', [
            'counts' => [
                'Users' => User::query()->count(),
                'Companies' => Company::query()->count(),
                'Projects' => Project::query()->count(),
                'Properties' => Property::query()->count(),
                'Listings' => Listing::query()->count(),
                'Media images' => MediaAsset::query()->count(),
                'New inquiries' => Inquiry::query()->where('status','new')->count(),
                'Contact messages' => ContactMessage::query()->where('status','new')->count(),
                'Viewing requests' => ViewingRequest::query()->where('status','pending')->count(),
                'Draft legal policies' => LegalPolicy::query()->where('status', 'draft')->count(),
            ],
            'import' => DemoImport::query()->latest()->first(),
            'recentInquiries' => Inquiry::query()->with('listing.property')->latest()->limit(5)->get(),
            'recentContacts' => ContactMessage::query()->latest()->limit(5)->get(),
        ]);
    }
}
