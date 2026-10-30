<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PublishedCompanySearch
{
    public function browse(): LengthAwarePaginator
    {
        return Company::query()
            ->publiclyVisible()
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(12);
    }

    public function findPublicBySlug(string $slug): Company
    {
        return Company::query()
            ->publiclyVisible()
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
