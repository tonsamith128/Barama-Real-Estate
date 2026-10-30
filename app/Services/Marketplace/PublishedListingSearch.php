<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use App\Models\Listing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class PublishedListingSearch
{
    /**
     * @param array<string, mixed> $filters Already validated by BrowsePropertiesRequest.
     */
    public function browse(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $query = Listing::query()
            ->visibleToPublic()
            ->with(['property.company', 'property.project', 'mediaAttachments.asset']);

        $keyword = trim((string) ($filters['q'] ?? ''));
        if ($keyword !== '') {
            $query->whereHas('property', function (Builder $property) use ($keyword): void {
                $property->where(function (Builder $match) use ($keyword): void {
                    $match->where('title', 'like', '%'.$keyword.'%')
                        ->orWhere('reference_code', 'like', '%'.$keyword.'%')
                        ->orWhereHas('company', fn (Builder $company) => $company
                            ->where('name', 'like', '%'.$keyword.'%'));
                });
            });
        }

        if (!empty($filters['offer'])) {
            $query->where('transaction_type', $filters['offer']);
        }

        if (!empty($filters['type'])) {
            $query->whereHas('property', fn (Builder $property) => $property
                ->where('property_type', $filters['type']));
        }

        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('published_at')->orderBy('id'),
            'price_asc' => $query->orderBy('price')->orderByDesc('published_at')->orderByDesc('id'),
            'price_desc' => $query->orderByDesc('price')->orderByDesc('published_at')->orderByDesc('id'),
            default => $query->orderByDesc('published_at')->orderByDesc('id'),
        };

        return $query->paginate(min(max($perPage, 1), 48))->withQueryString();
    }

    public function findPublicBySlug(string $slug): Listing
    {
        return Listing::query()
            ->visibleToPublic()
            ->with(['property.company', 'property.project', 'mediaAttachments.asset'])
            ->where('slug', $slug)
            ->firstOrFail();
    }
}
