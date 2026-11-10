<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use App\Models\Company;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Reuse the existing media attachments for the *current* public company
 * logo/cover. Never include pending revisions or history_* collections.
 */
final class PublishedCompanyMedia
{
    public const PUBLIC_COLLECTIONS = ['logo', 'cover'];

    /**
     * @param EloquentCollection<int, Company> $companies
     * @return array<int, array<string, MediaAsset>>
     */
    public function forCompanies(EloquentCollection $companies): array
    {
        if ($companies->isEmpty()) {
            return [];
        }

        $ids = Company::query()->publiclyVisible()
            ->whereIn('id', $companies->modelKeys())
            ->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $result = [];

        $attachments = MediaAttachment::query()->with('asset')
            ->where('entity_type', 'company')
            ->whereIn('entity_id', $ids)
            ->whereIn('collection', self::PUBLIC_COLLECTIONS)
            ->where('is_primary', true)
            ->orderBy('id')
            ->get();

        foreach ($attachments as $attachment) {
            $asset = $attachment->asset;
            if (!$asset instanceof MediaAsset
                || !in_array($asset->usage_rights, ['owned', 'licensed'], true)
                || !in_array($asset->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)
                || !str_starts_with((string) $asset->path, 'private/media/')) {
                continue;
            }

            // First valid row wins for a malformed legacy duplicate.
            if (!isset($result[$attachment->entity_id][$attachment->collection])) {
                $result[$attachment->entity_id][$attachment->collection] = $asset;
            }
        }

        return $result;
    }

    public function findPublicBySlug(string $slug, string $collection): MediaAsset
    {
        abort_unless(in_array($collection, self::PUBLIC_COLLECTIONS, true), 404);

        $company = Company::query()->publiclyVisible()->where('slug', $slug)->firstOrFail();
        $assets = $this->forCompanies(new EloquentCollection([$company]));
        $asset = $assets[$company->getKey()][$collection] ?? null;
        if (!$asset instanceof MediaAsset) {
            throw (new ModelNotFoundException())->setModel(MediaAsset::class, [$slug, $collection]);
        }

        return $asset;
    }
}
