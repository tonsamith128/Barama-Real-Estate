<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer\Media;

use App\Models\Company;
use App\Models\Listing;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Services\Media\ListingGalleryManager;
use App\Services\Media\PrivateImageLocator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ListingGalleryController
{
    public function store(
        Request $request, Company $company, Listing $listing,
        ListingGalleryManager $gallery
    ): RedirectResponse {
        $gallery->authorizeDraft($request->user(), $company, $listing);
        $data = $request->validate([
            'image' => ['required', 'file', 'max:12288'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);
        $gallery->upload(
            $request->user(), $company, $listing,
            $request->file('image'), $data['alt_text'] ?? null,
        );
        return redirect()->route('developer.properties.index', $company)
            ->with('notice', 'Private gallery image added.');
    }

    public function destroy(
        Request $request, Company $company, Listing $listing,
        MediaAttachment $attachment, ListingGalleryManager $gallery
    ): RedirectResponse {
        $gallery->detach($request->user(), $company, $listing, $attachment);
        return redirect()->route('developer.properties.index', $company)
            ->with('notice', 'Image detached from this listing.');
    }

    public function preview(
        Request $request, Company $company, Listing $listing,
        MediaAsset $asset, ListingGalleryManager $gallery,
        PrivateImageLocator $locator
    ): BinaryFileResponse {
        $gallery->authorizeDraft($request->user(), $company, $listing);
        abort_unless(MediaAttachment::query()->where([
            'media_asset_id' => $asset->getKey(),
            'entity_type' => 'listing',
            'entity_id' => $listing->getKey(),
        ])->exists(), 404);
        return $locator->response($asset);
    }
}
