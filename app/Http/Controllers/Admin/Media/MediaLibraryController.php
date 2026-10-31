<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Media;

use App\Models\MediaAsset;
use App\Services\Media\PrivateImageLocator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MediaLibraryController
{
    public function preview(Request $request, MediaAsset $asset, PrivateImageLocator $locator): BinaryFileResponse
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
        return $locator->response($asset);
    }

    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
        return view('admin.media.index', [
            'assets' => MediaAsset::query()->withCount('attachments')
                ->latest()->paginate(24),
        ]);
    }
}
