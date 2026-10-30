<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Home;

use Illuminate\Contracts\View\View;
use App\Models\Company;
use App\Models\Project;
use App\Models\Listing;

final class HomeController
{
    public function __invoke(): View
    {
        return view('frontend.home.index', [
            'listings' => Listing::query()->visibleToPublic()
                ->with(['property.company', 'property.project', 'mediaAttachments.asset'])
                ->orderByDesc('published_at')->limit(6)->get(),
            'propertyCount' => Listing::query()->visibleToPublic()->count(),
            'projectCount' => Project::query()->publiclyVisible()->count(),
            'companyCount' => Company::query()->publiclyVisible()->count(),
        ]);
    }
}
