<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Media;

use App\Models\MediaAsset;
use App\Services\Media\MediaMetadataManager;
use App\Services\Media\PrivateImageLocator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        $filters = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'rights' => ['sometimes', 'nullable', Rule::in(array_keys(MediaMetadataManager::RIGHTS))],
        ]);
        $term = trim($filters['q'] ?? '');
        $status = $filters['rights'] ?? '';
        $metadataReady = Schema::hasColumn('media_assets', 'usage_rights');

        $query = MediaAsset::query()->withCount('attachments');
        if ($term !== '') {
            $query->where(function ($builder) use ($term): void {
                $builder->where('original_name', 'like', '%'.$term.'%')
                    ->orWhere('alt_text', 'like', '%'.$term.'%');
            });
        }
        if ($status !== '' && $metadataReady) {
            $query->where('usage_rights', $status);
        }

        return view('admin.media.index', [
            'assets' => $query->latest()->paginate(20)->withQueryString(),
            'term' => $term,
            'rightsStatus' => $status,
            'rightsOptions' => MediaMetadataManager::RIGHTS,
            'metadataReady' => $metadataReady,
        ]);
    }

    public function updateMetadata(
        Request $request,
        MediaAsset $asset,
        MediaMetadataManager $metadata,
    ): RedirectResponse {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
        abort_unless(Schema::hasColumn('media_assets', 'usage_rights'), 409, 'Run database migrations before editing rights metadata.');

        $data = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:255'],
            'usage_rights' => ['required', Rule::in(array_keys(MediaMetadataManager::RIGHTS))],
            'rights_holder' => ['nullable', 'required_if:usage_rights,owned,licensed', 'string', 'max:190'],
            'rights_reference' => ['nullable', 'required_if:usage_rights,licensed', 'string', 'max:500'],
        ]);
        $metadata->update($request->user(), $asset, $data);

        return back()->with('notice', 'Image metadata saved. Ownership and licenses must still be independently verified.');
    }
}
