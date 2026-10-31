<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Home;

use Illuminate\Contracts\View\View;
use App\Models\Company;
use App\Models\Project;
use App\Models\Listing;
use App\Services\Appearance\HomepageMediaAccess;
use App\Services\Appearance\SiteAppearanceService;

final class HomeController
{
    public function __invoke(): View
    {
        $appearance = app(SiteAppearanceService::class)->published();
        $mediaAccess = app(HomepageMediaAccess::class);
        $images = collect();
        if ($mediaAccess->isReady()) {
            $ids = array_filter(array_merge(
                [$appearance['hero_image_asset_id'] ?? null],
                array_map(
                    static fn (array $section): ?int => !empty($section['enabled'])
                        ? ($section['image_asset_id'] ?? null) : null,
                    $appearance['home_sections'] ?? [],
                ),
            ));
            if ($ids !== []) {
                $images = $mediaAccess->selectable()->whereIn('id', $ids)->get()->keyBy('id');
            }
        }

        return view('frontend.home.index', [
            'homepageImages' => $images,
            'listings' => Listing::query()->visibleToPublic()
                ->with(['property.company', 'property.project', 'mediaAttachments.asset'])
                ->orderByDesc('published_at')->limit(6)->get(),
            'propertyCount' => Listing::query()->visibleToPublic()->count(),
            'projectCount' => Project::query()->publiclyVisible()->count(),
            'companyCount' => Company::query()->publiclyVisible()->count(),
        ]);
    }
}
