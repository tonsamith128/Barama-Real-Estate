<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Appearance;

use App\Services\Appearance\HomePageEditorService;
use App\Services\Appearance\HomepageMediaAccess;
use App\Services\Appearance\SiteAppearanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class HomePageBuilderController
{
    private function authorizeEditor(Request $request): void
    {
        abort_unless($request->user()?->isSystemAdministrator(), 403);
    }

    public function index(Request $request, SiteAppearanceService $appearance): View
    {
        $this->authorizeEditor($request);
        $media = app(HomepageMediaAccess::class);
        $term = trim((string) $request->query('media_q', ''));
        abort_if(mb_strlen($term) > 80, 422);
        return view('admin.appearance.homepage', [
            'mediaAssets' => $media->isReady()
                ? $media->selectable()
                    ->when($term !== '', fn ($q) => $q->where('original_name', 'like', '%'.$term.'%'))
                    ->latest()->limit(36)->get()
                : collect(),
            'mediaReady' => $media->isReady(),
            'mediaTerm' => $term,
            'draft' => $appearance->editing(),
            'published' => $appearance->published(),
            'types' => HomePageEditorService::TYPES,
            'menu' => HomePageEditorService::MENU,
        ]);
    }

    public function createSection(Request $request, HomePageEditorService $editor): RedirectResponse
    {
        $this->authorizeEditor($request);
        $editor->add($request->user(), $this->validateSection($request));
        return back()->with('notice', 'Homepage section added to your unpublished draft.');
    }

    public function updateSection(Request $request, string $section, HomePageEditorService $editor): RedirectResponse
    {
        $this->authorizeEditor($request);
        $editor->edit($request->user(), $section, $this->validateSection($request));
        return back()->with('notice', 'Draft section updated. Publish to make it public.');
    }

    public function removeSection(Request $request, string $section, HomePageEditorService $editor): RedirectResponse
    {
        $this->authorizeEditor($request);
        $editor->remove($request->user(), $section);
        return back()->with('notice', 'Draft section removed.');
    }

    public function moveSection(Request $request, string $section, HomePageEditorService $editor): RedirectResponse
    {
        $this->authorizeEditor($request);
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $editor->moveSection($request->user(), $section, $data['direction']);
        return back()->with('notice', 'Draft section order changed.');
    }

    public function changeHeroImage(Request $request, HomePageEditorService $editor): RedirectResponse
    {
        $this->authorizeEditor($request);
        $data = $request->validate([
            'image_asset_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $editor->changeHeroImage($request->user(), isset($data['image_asset_id'])
            ? (int) $data['image_asset_id'] : null);

        return back()->with('notice', 'Hero image saved to your unpublished draft.');
    }

    public function addLink(Request $request, HomePageEditorService $editor): RedirectResponse
    {
        $this->authorizeEditor($request);
        $data = $request->validate([
            'link' => ['required', Rule::in(array_keys(HomePageEditorService::MENU))],
        ]);
        $editor->addLink($request->user(), $data['link']);
        return back()->with('notice', 'Navigation link added to draft.');
    }

    public function removeLink(Request $request, string $link, HomePageEditorService $editor): RedirectResponse
    {
        $this->authorizeEditor($request);
        $editor->removeLink($request->user(), $link);
        return back()->with('notice', 'Navigation link removed from draft.');
    }

    public function moveLink(Request $request, string $link, HomePageEditorService $editor): RedirectResponse
    {
        $this->authorizeEditor($request);
        $data = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);
        $editor->moveLink($request->user(), $link, $data['direction']);
        return back()->with('notice', 'Navigation order updated in draft.');
    }

    private function validateSection(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(array_keys(HomePageEditorService::TYPES))],
            'title' => ['required', 'string', 'min:3', 'max:140'],
            'body' => ['nullable', 'string', 'max:1200'],
            'button_label' => ['nullable', 'required_if:type,cta', 'string', 'max:60'],
            'button_route' => ['required', Rule::in(array_keys(HomePageEditorService::MENU))],
            'enabled' => ['sometimes', 'boolean'],
            'image_asset_id' => ['nullable', 'integer', 'min:1'],
            'image_position' => ['required', Rule::in(['left', 'right'])],
        ]);
    }
}
