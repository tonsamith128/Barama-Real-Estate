<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend\Media;

use App\Services\Marketplace\PublicMediaAccess;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class PublicDemoMediaController
{
    public function __invoke(string $digest, PublicMediaAccess $access): BinaryFileResponse
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $digest) === 1, 404);
        $asset = $access->findByDigest($digest);
        $filename = basename($asset->path);
        abort_unless(preg_match('/^[a-f0-9]{64}\.(jpg|png|webp)$/D', $filename) === 1, 404);
        abort_unless(str_starts_with($filename, $digest.'.'), 404);
        $path = storage_path('app/private/demo/'.$filename);
        abort_unless(is_file($path) && !is_link($path), 404);
        return response()->file($path, [
            'Content-Type' => $asset->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
