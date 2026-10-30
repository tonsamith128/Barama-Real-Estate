<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use App\Models\Company;
use App\Models\Listing;
use App\Models\MediaAsset;
use App\Models\Project;
use App\Models\Property;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class PublicMediaAccess
{
    public function findByDigest(string $digest): MediaAsset
    {
        $asset = MediaAsset::query()->where('checksum_sha256', $digest)->firstOrFail();
        $visible = false;
        foreach ($asset->attachments as $attachment) {
            $id = $attachment->entity_id;
            $visible = match ($attachment->entity_type) {
                'company' => Company::query()->publiclyVisible()->whereKey($id)->exists(),
                'project' => Project::query()->publiclyVisible()->whereKey($id)->exists(),
                'property' => Property::query()->approved()->whereKey($id)
                    ->whereHas('listings', fn ($listings) => $listings->visibleToPublic())->exists(),
                'listing' => Listing::query()->visibleToPublic()->whereKey($id)->exists(),
                default => false,
            };
            if ($visible) {
                break;
            }
        }
        if (!$visible) {
            throw (new ModelNotFoundException())->setModel(MediaAsset::class, [$digest]);
        }
        return $asset;
    }
}
