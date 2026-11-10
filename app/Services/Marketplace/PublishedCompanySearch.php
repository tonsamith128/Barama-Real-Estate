<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class PublishedCompanySearch
{
    /** @param array<string, mixed> $filters Validated by BrowseCompaniesRequest. */
    public function browse(array $filters = []): LengthAwarePaginator
    {
        $query = Company::query()->publiclyVisible();

        $keyword = trim((string) ($filters['q'] ?? ''));
        if ($keyword !== '') {
            $query->where(function (Builder $match) use ($keyword): void {
                $match->where('name', 'like', '%'.$keyword.'%')
                    ->orWhere('description', 'like', '%'.$keyword.'%');
            });
        }

        if (!empty($filters['type'])) {
            $query->where('company_type', $filters['type']);
        }

        match ($filters['sort'] ?? 'name') {
            'newest' => $query->orderByDesc('published_at')->orderByDesc('id'),
            default => $query->orderBy('name')->orderBy('id'),
        };

        return $query->paginate(12)->withQueryString();
    }

    public function findPublicBySlug(string $slug): Company
    {
        return Company::query()
            ->publiclyVisible()
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
