<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Projects;

use App\Services\Marketplace\PublishedProjectSearch;
use Illuminate\Contracts\View\View;

final class ProjectController
{
    public function index(PublishedProjectSearch $search): View
    {
        return view('frontend.projects.index', [
            'projects' => $search->browse(),
        ]);
    }

    public function show(string $slug, PublishedProjectSearch $search): View
    {
        return view('frontend.projects.show', [
            'project' => $search->findPublicBySlug($slug),
        ]);
    }
}
