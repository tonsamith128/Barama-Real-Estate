<?php

declare(strict_types=1);

namespace App\Http\Controllers\Developer\Media;

use App\Models\Company;
use App\Models\MediaAsset;
use App\Models\MediaAttachment;
use App\Models\Project;
use App\Services\Media\PrivateImageLocator;
use App\Services\Media\ProjectGalleryManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ProjectGalleryController
{
    public function store(
        Request $request, Company $company, Project $project,
        ProjectGalleryManager $gallery,
    ): RedirectResponse {
        $gallery->authorizeDraft($request->user(), $company, $project);
        $data = $request->validate([
            'image' => ['required', 'file', 'max:12288'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);
        $gallery->upload($request->user(), $company, $project,
            $request->file('image'), $data['alt_text'] ?? null);
        return back()->with('notice', 'Project gallery image uploaded privately.');
    }

    public function destroy(
        Request $request, Company $company, Project $project,
        MediaAttachment $attachment, ProjectGalleryManager $gallery,
    ): RedirectResponse {
        $gallery->detach($request->user(), $company, $project, $attachment);
        return back()->with('notice', 'Project image detached. The original media is retained.');
    }

    public function order(
        Request $request, Company $company, Project $project,
        MediaAttachment $attachment, ProjectGalleryManager $gallery,
    ): RedirectResponse {
        $gallery->authorizeDraft($request->user(), $company, $project);
        $data = $request->validate([
            'action' => ['required', Rule::in(['primary', 'up', 'down'])],
        ]);
        $gallery->changeOrder($request->user(), $company, $project, $attachment, $data['action']);
        return back()->with('notice', 'Project photo order saved.');
    }

    public function preview(
        Request $request, Company $company, Project $project,
        MediaAsset $asset, ProjectGalleryManager $gallery,
        PrivateImageLocator $locator,
    ): BinaryFileResponse {
        $gallery->authorizeDraft($request->user(), $company, $project);
        abort_unless(MediaAttachment::query()->where([
            'entity_type' => 'project',
            'entity_id' => $project->getKey(),
            'media_asset_id' => $asset->getKey(),
            'collection' => 'gallery',
        ])->exists(), 404);

        return $locator->response($asset);
    }
}
