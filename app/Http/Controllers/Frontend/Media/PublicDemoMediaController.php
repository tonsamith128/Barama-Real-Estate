<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Media;

use App\Services\Marketplace\PublicMediaAccess;
use App\Services\Media\PrivateImageLocator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class PublicDemoMediaController
{
    public function __invoke(string $digest, PublicMediaAccess $access, PrivateImageLocator $locator): BinaryFileResponse
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $digest) === 1, 404);
        $asset = $access->findByDigest($digest);
        return $locator->response($asset, public: true);
    }
}
