<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Media;

use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MediaLibraryController
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
        return view('admin.media.index', [
            'assets' => MediaAsset::query()->withCount('attachments')
                ->latest()->paginate(24),
        ]);
    }
}
