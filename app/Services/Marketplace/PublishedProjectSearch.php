<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PublishedProjectSearch
{
    public function browse(): LengthAwarePaginator
    {
        return Project::query()
            ->publiclyVisible()
            ->with('company')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(12);
    }

    public function findPublicBySlug(string $slug): Project
    {
        return Project::query()
            ->publiclyVisible()
            ->with('company')
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
