<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Projects;

use App\Domain\RealEstate\MarketplaceStatus;
use App\Http\Requests\Frontend\Projects\BrowseProjectsRequest;
use App\Services\Marketplace\PublishedProjectSearch;
use Illuminate\Contracts\View\View;

final class ProjectController
{
    public function index(BrowseProjectsRequest $request, PublishedProjectSearch $search): View
    {
        $filters = $request->validated();

        return view('frontend.projects.index', [
            'projects' => $search->browse($filters),
            'filters' => $filters,
            'projectTypes' => MarketplaceStatus::PROJECT_TYPES,
            'completionStages' => MarketplaceStatus::PROJECT_COMPLETION_STATES,
        ]);
    }

    public function show(string $slug, PublishedProjectSearch $search): View
    {
        return view('frontend.projects.show', [
            'project' => $search->findPublicBySlug($slug),
        ]);
    }
}
