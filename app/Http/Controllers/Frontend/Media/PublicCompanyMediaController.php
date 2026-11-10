<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Media;

use App\Services\Marketplace\PublishedCompanyMedia;
use App\Services\Media\PrivateImageLocator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class PublicCompanyMediaController
{
    public function __invoke(
        string $slug,
        string $collection,
        PublishedCompanyMedia $media,
        PrivateImageLocator $locator,
    ): BinaryFileResponse {
        $asset = $media->findPublicBySlug($slug, $collection);

        // This stable URL may change its underlying asset after approval.
        // Do not reuse the long-lived cache policy for immutable digest URLs.
        return $locator->response($asset);
    }
}
