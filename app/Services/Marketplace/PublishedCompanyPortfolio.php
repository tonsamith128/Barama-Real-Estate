<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use App\Models\Company;
use App\Models\Listing;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class PublishedCompanyPortfolio
{
    /**
     * Display a limited, published-only preview without exposing company draft records.
     * @return array{projects: Collection<int, Project>, listings: Collection<int, Listing>}
     */
    public function preview(Company $company): array
    {
        $company = Company::query()->publiclyVisible()->findOrFail($company->getKey());

        $projects = Project::query()->publiclyVisible()
            ->where('company_id', $company->getKey())
            ->orderByDesc('published_at')->orderByDesc('id')
            ->limit(6)->get();

        $listings = Listing::query()->visibleToPublic()
            ->whereHas('property', fn (Builder $property) => $property
                ->where('company_id', $company->getKey()))
            ->with(['property.company', 'property.project', 'mediaAttachments.asset'])
            ->orderByDesc('published_at')->orderByDesc('id')
            ->limit(6)->get();

        return ['projects' => $projects, 'listings' => $listings];
    }
}
