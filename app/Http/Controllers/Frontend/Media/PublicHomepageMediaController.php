<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Media;

use App\Models\MediaAsset;
use App\Services\Appearance\HomepageMediaAccess;
use App\Services\Media\PrivateImageLocator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class PublicHomepageMediaController
{
    public function __invoke(
        MediaAsset $asset,
        HomepageMediaAccess $access,
        PrivateImageLocator $locator,
    ): BinaryFileResponse {
        abort_unless($access->publiclySelected($asset), 404);

        return $locator->response($asset, public: true);
    }
}
