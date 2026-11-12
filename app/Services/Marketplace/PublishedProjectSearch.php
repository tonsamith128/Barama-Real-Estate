<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PublishedProjectSearch
{
    /** @param array<string,mixed> $filters validated public browse parameters. */
    public function browse(array $filters = []): LengthAwarePaginator
    {
        $query = Project::query()->publiclyVisible()->with('company');
        $keyword = trim((string) ($filters['q'] ?? ''));
        if ($keyword !== '') {
            $query->where(function (\Illuminate\Database\Eloquent\Builder $match) use ($keyword): void {
                $match->where('name', 'like', '%'.$keyword.'%')
                    ->orWhereHas('company', fn (\Illuminate\Database\Eloquent\Builder $company) =>
                        $company->where('name', 'like', '%'.$keyword.'%'));
            });
        }
        if (!empty($filters['type'])) {
            $query->where('project_type', $filters['type']);
        }
        if (!empty($filters['stage'])) {
            $query->where('completion_status', $filters['stage']);
        }
        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('published_at')->orderBy('id'),
            'name' => $query->orderBy('name')->orderBy('id'),
            default => $query->orderByDesc('published_at')->orderByDesc('id'),
        };
        return $query->paginate(12)->withQueryString();
    }

    public function findPublicBySlug(string $slug): Project
    {
        return Project::query()
            ->publiclyVisible()
            ->with(['company', 'amenities' => fn ($query) => $query->active(), 'milestones'])
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
